<?php declare(strict_types=1);

namespace Sourcerer\Error;

use STDW\Contract\Container\ContainerInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;

use Sourcerer\Error\Exception\HttpException;
use Sourcerer\Log\LogInterface;

use ErrorException;
use RuntimeException;
use Throwable;

use function Sourcerer\config;
use function Sourcerer\env;


/**
 * Turns any throwable into an HTTP response.
 *
 * Opinionated framework init: reads environment and error settings directly
 * from global helpers config() and env() with safe defaults.
 */
final class ErrorHandler
{
    private readonly bool $development;

    private readonly bool $json;

    /** @var array<int|string, mixed> */
    private readonly array $messages;

    private readonly ErrorRenderer $renderer;


    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $env = env('environment', 'production');
        $mode = config('error.mode', 'html');
        $messages = config('error.messages', []);
        $pad = config('error.snippet.pad', 10);

        $this->development = is_string($env) && $env === 'development';
        $this->json = is_string($mode) && strtolower($mode) === 'json';
        $this->messages = is_array($messages) ? $messages : [];

        if ( ! is_int($pad)) {
            $pad = is_numeric($pad) ? (int) $pad : 10;
        }

        $this->renderer = new ErrorRenderer($pad);
    }


    public static function register(string $environment = 'development'): void
    {
        if ($environment !== 'development') {
            return;
        }

        error_reporting(\E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (($severity & (\E_DEPRECATED | \E_USER_DEPRECATED)) !== 0) {
                return false;
            }

            if ( ! (error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }


    public function handle(Throwable $error, RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = Fallback::id();
        $http = $error instanceof HttpException ? $error : null;
        $status = $http?->status() ?? 500;
        $json = $this->wantsJson($request);

        $this->log($id, $error, $request, $status);

        /** @var array<string, mixed> $override */
        $override = $this->override($status);

        try {
            $body = $this->renderer->render($error, $id, $status, $this->development, $json, $override);
        } catch (Throwable $failure) {
            error_log('error renderer: ' . $failure::class . ': ' . self::clean($failure->getMessage()));

            $status = 500;
            $body = $json
                ? '{"status":"error","id":"' . $id . '","code":500,"title":"Internal Server Error","message":"Internal Server Error"}'
                : Fallback::document($id);
        }

        $response = $response
            ->withStatus($status)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Content-Type', $json ? 'application/json; charset=utf-8' : 'text/html; charset=utf-8');

        if ($status >= 500) {
            $response = $response->withHeader('X-Error-Id', $id);
        }

        if ($http !== null) {
            foreach ($http->headers() as $name => $value) {
                if (preg_match('/^[A-Za-z0-9-]+$/', $name) !== 1) {
                    continue;
                }

                $response = $response->withHeader($name, self::clean($value));
            }
        }

        return $response->withBody($body);
    }


    private function log(string $id, Throwable $error, RequestInterface $request, int $status): void
    {
        $message = self::clean($error->getMessage());

        try {
            $uri = $request->getUri();

            $context = [
                'id' => $id,
                'status' => $status,
                'type' => $error::class,
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'method' => $request->getMethod(),
                'path' => self::clean($uri->getPath()),
                'query' => http_build_query($uri->getQuery()),
                'ip' => $this->clientIp($request),
                'ua' => self::clean($request->getHeader('User-Agent') ?? ''),
            ];

            if ($status >= 500) {
                $context['trace'] = $error->getTraceAsString();
            }

            $logger = $this->container->get(LogInterface::class);

            if ( ! $logger instanceof LogInterface) {
                throw new RuntimeException('log service does not implement ' . LogInterface::class);
            }

            $logger->error($message, $context);
            return;
        } catch (Throwable) {
            // fall through to error_log
        }

        error_log($message . ' [' . $id . ']');
    }

    /** @return array<string, mixed>
     */
    private function override(int $status): array
    {
        $override = $this->messages[$status] ?? [];

        if ( ! is_array($override)) {
            return [];
        }

        /** @var array<string, mixed> $override */
        return $override;
    }

    private function clientIp(RequestInterface $request): string
    {
        $forwarded = $request->getHeader('X-Forwarded-For');

        if ($forwarded !== null && $forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);

            if ($first !== '') {
                return self::clean($first);
            }
        }

        $client = $request->getHeader('Client-IP');

        if ($client !== null && $client !== '') {
            return self::clean($client);
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';

        return is_string($remote) ? self::clean($remote) : '';
    }

    private function wantsJson(RequestInterface $request): bool
    {
        if ($this->json) {
            return true;
        }

        $accept = $request->getHeader('Accept');

        return $accept !== null && str_contains(strtolower($accept), 'application/json');
    }


    private static function clean(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], ' ', $value);
    }
}

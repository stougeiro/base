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
 * Opinionated framework init: bootstrap() secures error reporting before
 * configuration. The constructor reads the environment and error settings from
 * config() and env(); a missing or invalid environment falls back to
 * production.
 */
final class ErrorHandler
{
    /** Substrings matched case-insensitively against query keys. */
    private const REDACT = [
        'password',
        'passwd',
        'pwd',
        'token',
        'secret',
        'key',
        'authorization',
        'cookie',
        'session',
        'csrf',
        'auth',
        'jwt',
        'signature',
        'otp',
        'pin',
        'cvv',
        'card',
    ];

    private const DEVELOPMENT = 'development';

    private const STAGING = 'staging';

    private const PRODUCTION = 'production';


    private readonly string $environment;

    private readonly bool $development;

    private readonly bool $json;

    /** @var array<int|string, mixed> */
    private readonly array $messages;

    /** @var list<string> */
    private readonly array $sensitive;

    private readonly ErrorRenderer $renderer;


    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $env = self::environment();
        $mode = config('error.mode', 'html');
        $messages = config('error.messages', []);
        $pad = config('error.snippet.pad', 10);
        $redact = config('error.log.redact', self::REDACT);

        $this->environment = $env;
        $this->development = $env === self::DEVELOPMENT;
        $this->json = is_string($mode) && strtolower($mode) === 'json';
        $this->messages = is_array($messages) ? $messages : [];
        $this->sensitive = array_values(array_unique([
            ...self::REDACT,
            ...self::normalizeRedact($redact),
        ]));

        if ( ! is_int($pad)) {
            $pad = is_numeric($pad) ? (int) $pad : 10;
        }

        $this->renderer = new ErrorRenderer($pad);
    }


    /**
     * Secures error reporting before configuration and enables development
     * visibility only when the environment says so.
     *
     * It deliberately does not use config(), so it can run while configuration
     * is still unreadable.
     */
    public static function bootstrap(): void
    {
        // Baseline before env(): a broken env.json must not leave display on.
        self::harden();

        if (self::environment() !== self::DEVELOPMENT) {
            return;
        }

        // The environment is known and development, so expose the details.
        self::harden(true);
        self::register();
    }

    /**
     * Logs the throwable and renders it as an HTML or JSON response.
     */
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


    /**
     * Resolves the environment without ever exposing an invalid value.
     *
     * A missing or malformed env.json is treated as production, both here and
     * in bootstrap(), so failures fall back to the safe baseline.
     */
    private static function environment(): string
    {
        try {
            $value = env('environment');
        } catch (Throwable) {
            return self::PRODUCTION;
        }

        return match ($value) {
            self::DEVELOPMENT => self::DEVELOPMENT,
            self::STAGING => self::STAGING,
            self::PRODUCTION => self::PRODUCTION,

            default => self::PRODUCTION,
        };
    }

    /**
     * Safe error settings for deployments that cannot edit php.ini.
     *
     * Private on purpose: bootstrap() is the only caller and passes the mode
     * explicitly so the safe baseline runs before the environment is read.
     */
    private static function harden(bool $development = false): void
    {
        ini_set('display_errors', $development ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('zend.exception_ignore_args', $development ? '0' : '1');

        error_reporting(\E_ALL);
    }

    /**
     * Installs the development error handler.
     *
     * bootstrap() calls it only after harden(true), so error_reporting is
     * already E_ALL and no environment argument is needed.
     */
    private static function register(): void
    {
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

    /**
     * @return list<string>
     */
    private static function normalizeRedact(mixed $redact): array
    {
        if ( ! is_array($redact)) {
            return [];
        }

        $keys = [];

        foreach ($redact as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = strtolower($key);
            }
        }

        return $keys;
    }

    private static function clean(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], ' ', $value);
    }


    /**
     * Logs through the container logger, falling back to error_log.
     */
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
                'query' => http_build_query($this->redact($uri->getQuery())),
                'ip' => $this->clientIp($request),
                'ua' => self::clean($request->getHeader('User-Agent') ?? ''),
            ];

            if ($status >= 500 || $this->environment === self::STAGING) {
                // harden() hides argument values outside development.
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

    /**
     * @param array<mixed> $query
     * @return array<mixed>
     */
    private function redact(array $query): array
    {
        $redacted = [];

        foreach ($query as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    /**
     * Case-insensitive substring match: catches api_key/accessToken variants;
     * a false positive only hides a query parameter from the log.
     */
    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->sensitive as $term) {
            if (str_contains($key, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
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

    /**
     * Best-effort client IP for logs. Forwarded headers are client-controlled,
     * so this must not be used for authorization decisions.
     */
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
}

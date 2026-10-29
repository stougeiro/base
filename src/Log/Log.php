<?php declare(strict_types=1);

namespace Sourcerer\Log;

use Sourcerer\Path;
use Throwable;


/**
 * Application logger that writes formatted log entries with optional context
 * to the configured log file, falling back to error_log on failure.
 */
final class Log implements LogInterface
{
    public const ERROR = 0;
    public const WARN = 1;
    public const INFO = 2;
    public const DEBUG = 3;

    private const LABELS = [
        self::ERROR => 'ERROR',
        self::WARN => 'WARN',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG',
    ];

    private const MESSAGE_LIMIT = 4096;

    private const CONTEXT_LIMIT = 8192;


    public function __construct(
        private readonly ?string $file = null,
    ) { }


    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write($message, self::ERROR, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warn(string $message, array $context = []): void
    {
        $this->write($message, self::WARN, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write($message, self::INFO, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write($message, self::DEBUG, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function write(string $message, int $level, array $context = []): void
    {
        $date = date('[Y-m-d H:i:s] ');
        $label = self::LABELS[$level] ?? 'DEBUG';
        $message = self::clean($message);

        if (strlen($message) > self::MESSAGE_LIMIT) {
            $message = substr($message, 0, self::MESSAGE_LIMIT) .'...';
        }

        $line = $date . $label .' '. $message;

        if ( ! empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            if ($json !== false) {
                if (strlen($json) > self::CONTEXT_LIMIT) {
                    $json = '{"_truncated":true,"bytes":'. strlen($json) .'}';
                }

                $line .= ' '. $json;
            }
        }

        $formatted = $line . PHP_EOL;

        try {
            $logFile = $this->file ?? Path::get('logs') .'app.log';

            if (file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        } catch (Throwable $e) {
            error_log($date .'ERROR '. self::clean($e->getMessage()));
        }

        error_log($formatted);
    }


    private static function clean(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? $value;
    }
}

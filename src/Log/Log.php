<?php declare(strict_types=1);

namespace Sourcerer;

use Throwable;


/**
 * Application logger that writes formatted log entries with optional context
 * to the configured log file, falling back to error_log on failure.
 */
final class Log
{
    public const ERROR = 0;
    public const WARN = 1;
    public const INFO = 2;
    public const DEBUG = 3;


    private function __construct()
    { }


    /**
     * Log an error message.
     *
     * @param string $message 
     * @param array<string, mixed> $context
     * @return void 
     */
    public static function error(string $message, array $context = []): void
    {
        self::write($message, self::ERROR, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message 
     * @param array<string, mixed> $context
     * @return void 
     */
    public static function warn(string $message, array $context = []): void
    {
        self::write($message, self::WARN, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message 
     * @param array<string, mixed> $context
     * @return void 
     */
    public static function info(string $message, array $context = []): void
    {
        self::write($message, self::INFO, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message 
     * @param array<string, mixed> $context
     * @return void 
     */
    public static function debug(string $message, array $context = []): void
    {
        self::write($message, self::DEBUG, $context);
    }

    /**
     * Write a log entry with a specific level and optional context.
     *
     * @param string $message 
     * @param int $level 
     * @param array<string, mixed> $context
     * @return void 
     */
    public static function write(string $message, int $level, array $context = []): void
    {
        $date = date('[Y-m-d H:i:s] ');
        $label = strtoupper(self::label($level));
        $line = $date . $label . $message;

        if ( ! empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            if ($json !== false) {
                $line .= ' '. $json;
            }
        }

        $formatted = $line . PHP_EOL;

        try {
            $logDir = Path::get('logs');
            $logFile = $logDir .'app.log';

            if (file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        } catch (Throwable $e) {
            error_log($date .'ERROR '. $e->getMessage());
        }

        error_log($formatted);
    }


    /**
     * @param int $level 
     * @return string 
     */
    private static function label(int $level): string
    {
        return match ($level) {
            0 => 'ERROR ',
            1 => 'WARN ',
            2 => 'INFO ',
            default => 'DEBUG ',
        };
    }
}

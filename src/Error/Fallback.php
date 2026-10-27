<?php declare(strict_types=1);

namespace Sourcerer\Error;

use Throwable;


/**
 * Last-resort 500 answer.
 *
 * It deliberately depends on nothing but PHP itself: no container, config,
 * logger, view, response or any other project class. The file is listed in
 * composer "autoload.files" so the class exists even when boot or the PSR-4
 * autoloader fails later.
 */
final class Fallback
{
    private const REASON = 'Internal Server Error';


    /**
     * Emits a plain 500 and terminates the request.
     *
     * This is the final frontier: it must never throw because of a missing
     * service or configuration.
     */
    public static function emit(Throwable $error): never
    {
        $id = self::id();

        while (ob_get_level() > 0) {
            if ( ! ob_end_clean()) {
                break;
            }
        }

        self::log($id, $error);
        self::sendHeaders($id);

        echo self::document($id);

        exit(1);
    }

    /**
     * The minimal HTML body, safe to build at any time.
     */
    public static function document(string $id): string
    {
        $reference = htmlspecialchars($id, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>500 ' . self::REASON . '</title>'
            . '<style>html{color-scheme:light dark}body{align-items:center;display:flex;font-family:sans-serif;justify-content:center;margin:0;min-height:100vh;text-align:center}main{padding:2rem}h1{font-size:250%;margin:0}h2{font-weight:500}.reference{opacity:.7}</style>'
            . '</head><body><main><h1>500</h1><h2>' . self::REASON . '</h2>'
            . '<p class="reference">Reference: ' . $reference . '</p></main></body></html>';
    }

    public static function id(): string
    {
        try {
            return strtoupper(bin2hex(random_bytes(8)));
        } catch (Throwable) {
            return strtoupper(dechex(time()) . dechex(mt_rand()));
        }
    }


    private static function log(string $id, Throwable $error): void
    {
        error_log(sprintf(
            '[%s] %s: %s in %s:%d',
            $id,
            $error::class,
            self::clean($error->getMessage()),
            $error->getFile(),
            $error->getLine(),
        ));
    }

    private static function sendHeaders(string $id): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Error-Id: ' . $id);
    }

    private static function clean(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], ' ', $value);
    }
}

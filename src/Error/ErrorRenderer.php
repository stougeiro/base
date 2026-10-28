<?php declare(strict_types=1);

namespace Sourcerer\Error;

use SplFileObject;
use Throwable;


/**
 * Renders the error body as HTML or JSON.
 *
 * Pure presentation: it receives everything it needs and owns no service.
 * Titles and messages stay raw until the moment of escaping, so a value is
 * escaped exactly once.
 */
final class ErrorRenderer
{
    private const REASONS = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        410 => 'Gone',
        422 => 'Unprocessable Content',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    private const STYLE = 'html{color-scheme:light dark}body{align-items:center;display:flex;font-family:sans-serif;justify-content:center;margin:0;min-height:100vh;text-align:center}main{padding:2rem;max-width:80rem}h1{font-size:250%;margin:0}h2{font-weight:500}.reference{opacity:.7}table{border-collapse:collapse;margin:1rem auto 0;text-align:left;font-size:.85rem}td{padding:.1rem .5rem;vertical-align:top}td:first-child{opacity:.5;text-align:right;user-select:none}.hit{background:rgba(255,0,0,.15)}pre{margin:0;white-space:pre-wrap;word-break:break-word}';

    private readonly int $snippetPad;


    public function __construct(int $snippetPad = 10)
    {
        $this->snippetPad = max(5, min(30, $snippetPad));
    }


    /** @param array<string, mixed> $override The configured entry for the status.
     */
    public function render(Throwable $error, string $id, int $status, bool $development, bool $json, array $override = []): string
    {
        return $json
            ? $this->json($error, $id, $status, $development, $override)
            : $this->html($error, $id, $status, $development, $override);
    }


    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function reason(int $status): string
    {
        return self::REASONS[$status] ?? 'Error';
    }


    /** @param array<string, mixed> $override
     */
    private function html(Throwable $error, string $id, int $status, bool $development, array $override): string
    {
        $title = $this->title($status, $override);
        $message = $this->message($error, $status, $development, $override);

        $detail = $message !== '' && $message !== $title
            ? '<p>' . self::escape($message) . '</p>'
            : '';

        $reference = $status >= 500
            ? '<p class="reference">Reference: ' . self::escape($id) . '</p>'
            : '';

        $snippet = $development ? $this->snippet($error->getFile(), $error->getLine()) : '';
        $trace = $development ? '<pre>' . self::escape($error->getTraceAsString()) . '</pre>' : '';

        $body = '<main><h1>' . $status . '</h1><h2>' . self::escape($title) . '</h2>'
            . $detail . $reference . $snippet . $trace . '</main>';

        return $this->document($status . ' ' . $title, $body);
    }

    /** @param array<string, mixed> $override
     */
    private function json(Throwable $error, string $id, int $status, bool $development, array $override): string
    {
        $payload = [
            'status' => 'error',
            'id' => $id,
            'code' => $status,
            'title' => $this->title($status, $override),
            'message' => $this->message($error, $status, $development, $override),
        ];

        if ($development) {
            $payload['details'] = [
                'type' => $error::class,
                'code' => $error->getCode(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
            ];
        }

        $json = json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? '{"status":"error","message":"Internal Server Error"}' : $json;
    }

    /** @param array<string, mixed> $override
     */
    private function title(int $status, array $override): string
    {
        $title = $override['title'] ?? null;

        return is_string($title) && $title !== '' ? $title : self::reason($status);
    }

    /** @param array<string, mixed> $override
     */
    private function message(Throwable $error, int $status, bool $development, array $override): string
    {
        $message = $override['message'] ?? null;

        if (is_string($message)) {
            return $message;
        }

        if ($development || $status < 500) {
            return $error->getMessage();
        }

        return self::reason($status);
    }

    private function snippet(string $file, int $line): string
    {
        if ($line < 1 || ! is_readable($file)) {
            return '';
        }

        $first = max($line - $this->snippetPad, 1);
        $last = $line + $this->snippetPad;

        try {
            $handle = new SplFileObject($file);
            $handle->seek($first - 1);
        } catch (Throwable) {
            return '';
        }

        $rows = '';

        while ($handle->valid()) {
            $number = $handle->key() + 1;

            if ($number > $last) {
                break;
            }

            $code = $handle->current();
            $class = $number === $line ? ' class="hit"' : '';
            $rows .= '<tr' . $class . '><td>' . $number . '</td><td><pre>' . self::escape(rtrim(is_string($code) ? $code : '')) . '</pre></td></tr>';

            $handle->next();
        }

        return $rows === '' ? '' : '<table>' . $rows . '</table>';
    }

    private function document(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . self::escape($title) . '</title>'
            . '<style>' . self::STYLE . '</style>'
            . '</head><body>' . $body . '</body></html>';
    }
}

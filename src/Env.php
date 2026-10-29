<?php declare(strict_types=1);

namespace Sourcerer;

use RuntimeException;
use JsonException;


/**
 * Reads a dot-notated item from the environment file at the project root.
 *
 * The file is read once per root. The path is resolved with realpath() and
 * cached per process, so repeated calls cost no extra stat. Every key is
 * required: a missing file or key throws instead of silently returning a
 * default.
 *
 * @param string $path Dot-notated key (e.g. "app.name").
 * @return mixed
 */
function env(string $path): mixed
{
    /** @var array<string, array<string, mixed>> $items */
    static $items = [];

    if ($path === '' || str_contains($path, "\0")) {
        throw new RuntimeException('env: path must not be empty or contain null bytes');
    }

    $segments = explode('.', $path);

    if (in_array('', $segments, true)) {
        throw new RuntimeException("env: invalid path '{$path}'");
    }

    $root = Path::get('root');

    if ( ! array_key_exists($root, $items)) {
        $items[$root] = (static function (string $root): array {
            $expected = $root . 'env.json';

            if (is_link($expected)) {
                throw new RuntimeException("env: file 'env.json' must not be a symbolic link");
            }

            $file = realpath($expected);

            if ($file === false || ! is_file($file) || ! is_readable($file)) {
                throw new RuntimeException("env: file 'env.json' not found or not readable");
            }

            $size = filesize($file);

            if ($size === false || $size > 1_048_576) {
                throw new RuntimeException("env: file 'env.json' is too large");
            }

            $raw = file_get_contents($file);

            // Defensive: is_readable() passed, so this only guards a TOCTOU/I/O failure.
            // @codeCoverageIgnoreStart
            if ($raw === false) {
                throw new RuntimeException("env: file 'env.json' could not be read");
            }
            // @codeCoverageIgnoreEnd

            $first = ltrim($raw);

            if ($first === '' || $first[0] !== '{') {
                throw new RuntimeException("env: file 'env.json' must contain a JSON object");
            }

            try {
                $loaded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new RuntimeException("env: file 'env.json' contains invalid JSON", 0, $e);
            }

            /** @var array<string, mixed> $loaded */
            return $loaded;
        })($root);
    }

    $item = $items[$root];

    foreach ($segments as $segment) {
        if ( ! is_array($item) || ! array_key_exists($segment, $item)) {
            throw new RuntimeException("env: item '{$path}' not found");
        }

        $item = $item[$segment];
    }

    return $item;
}

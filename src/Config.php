<?php declare(strict_types=1);

namespace Sourcerer;

use RuntimeException;


/**
 * Reads a dot-notated item from a configuration file in config/.
 *
 * The first segment is the file name; the remaining segments walk the
 * returned array. When a default is given, a missing file or key answers
 * it instead of throwing.
 *
 * Each file is read once per request; realpath() resolves and caches the
 * path per process, so the file lookup costs no extra stat after the first
 * call.
 *
 * @param string $path Dot-notated key (e.g. "app.providers").
 * @param mixed $default Value returned when the file or key is missing.
 * @return mixed
 */
function config(string $path, mixed $default = null): mixed
{
    static $items = [];

    $hasDefault = func_num_args() > 1;
    $segments = explode('.', $path);
    $filename = $segments[0];

    if ($filename === '' || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $filename) !== 1) {
        throw new RuntimeException('config: invalid file name');
    }

    if ( ! array_key_exists($filename, $items)) {
        try {
            $configPath = Path::get('config');
        } catch (RuntimeException $e) {
            if ($hasDefault) {
                return $default;
            }

            throw $e;
        }

        $file = realpath($configPath . $filename .'.php');
        $prefix = rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (
               $file === false
            || ! str_starts_with($file, $prefix)
            || ! is_file($file)
            || ! is_readable($file)
        ) {
            if ($hasDefault) {
                return $default;
            }

            throw new RuntimeException("config: file 'config/{$filename}.php' not found");
        }

        $loaded = include $file;

        if ( ! is_array($loaded)) {
            throw new RuntimeException("config: file 'config/{$filename}.php' must return an array");
        }

        $items[$filename] = $loaded;
    }

    $item = $items[$filename];

    for ($index = 1, $count = count($segments); $index < $count; $index++) {
        $segment = $segments[$index];

        if ( ! is_array($item) || ! array_key_exists($segment, $item)) {
            if ($hasDefault) {
                return $default;
            }

            throw new RuntimeException("config: item '{$path}' not found");
        }

        $item = $item[$segment];
    }

    return $item;
}

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

    if ( ! isset($items[$filename])) {
        $file = realpath(Path::get('config') . $filename .'.php');

        if ($file === false) {
            if ($hasDefault) {
                return $default;
            }

            throw new RuntimeException("config: file 'config/{$filename}.php' not found");
        }

        $items[$filename] = include $file;
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

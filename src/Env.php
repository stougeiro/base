<?php declare(strict_types=1);

namespace Sourcerer;

use RuntimeException;


/**
 * Reads a dot-notated item from the environment file at the project root.
 *
 * The file is read once per request. The path is resolved with realpath(),
 * whose result is cached per process, so repeated calls cost no extra stat.
 * When a default is given, a missing file or key answers it instead of
 * throwing.
 *
 * @param string $path Dot-notated key (e.g. "app.name").
 * @param mixed $default Value returned when the file or key is missing.
 * @return mixed
 */
function env(string $path, mixed $default = null): mixed
{
    static $items = null;

    $hasDefault = func_num_args() > 1;

    if ($items === null) {
        try {
            $root = Path::get('root');
        } catch (RuntimeException $e) {
            if ($hasDefault) {
                return $default;
            }

            throw $e;
        }

        $file = realpath($root .'env.php');

        if ($file === false) {
            if ($hasDefault) {
                return $default;
            }

            throw new RuntimeException("env: file 'env.php' not found");
        }

        $loaded = include $file;

        if ( ! is_array($loaded)) {
            throw new RuntimeException("env: file 'env.php' must return an array");
        }

        $items = $loaded;
    }

    $item = $items;

    foreach (explode('.', $path) as $segment) {
        if ( ! is_array($item) || ! array_key_exists($segment, $item)) {
            if ($hasDefault) {
                return $default;
            }

            throw new RuntimeException("env: item '{$path}' not found");
        }

        $item = $item[$segment];
    }

    return $item;
}

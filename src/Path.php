<?php declare(strict_types=1);

namespace Sourcerer;

use RuntimeException;


/**
 * Centralizes path registration and resolution for the process.
 */
final class Path
{
    /** @var array<string, string> */
    private static array $paths = [];


    private function __construct()
    { }


    /**
     * Register a path for a given key.
     */
    public static function register(string $key, string $path, string $from = ''): void
    {
        if ($key === '') {
            throw new RuntimeException('Path key must not be empty');
        }

        if (isset(self::$paths[$key])) {
            throw new RuntimeException("Path key '{$key}' is already registered");
        }

        $from = trim($from);

        if ($from !== '') {
            $from = self::get($from);
        }

        $realpath = realpath($from . $path);

        if ($realpath === false || ! is_dir($realpath)) {
            throw new RuntimeException("Path key '{$key}' is not valid: {$from}{$path}");
        }

        self::$paths[$key] = rtrim($realpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Resolve the path for a given key.
     */
    public static function get(string $key): string
    {
        if ( ! isset(self::$paths[$key])) {
            throw new RuntimeException("Path key '{$key}' not registered");
        }

        return self::$paths[$key];
    }
}

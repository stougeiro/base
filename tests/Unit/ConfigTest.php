<?php declare(strict_types=1);

use Sourcerer\Path;
use function Sourcerer\config;

beforeEach(fn () => reset_path());

test('config reads dot-notated item from file', function () {
    $dir = temp_dir('config_');
    file_put_contents($dir . '/cf_read.php', '<?php return ["name" => "Sourcerer", "nested" => ["key" => "value"]];');

    try {
        Path::register('config', $dir);

        expect(config('cf_read.name'))->toBe('Sourcerer')
            ->and(config('cf_read.nested.key'))->toBe('value');
    } finally {
        remove_file($dir . '/cf_read.php');
        remove_dir($dir);
    }
});

test('config returns default when file missing', function () {
    $dir = temp_dir('config_');

    try {
        Path::register('config', $dir);

        expect(config('cf_missing.key', 'default_val'))->toBe('default_val');
    } finally {
        remove_dir($dir);
    }
});

test('config returns default when key missing', function () {
    $dir = temp_dir('config_');
    file_put_contents($dir . '/cf_key.php', '<?php return [];');

    try {
        Path::register('config', $dir);

        expect(config('cf_key.missing', 'fallback'))->toBe('fallback');
    } finally {
        remove_file($dir . '/cf_key.php');
        remove_dir($dir);
    }
});

test('config throws when no default and file missing', function () {
    $dir = temp_dir('config_');

    try {
        Path::register('config', $dir);

        expect(fn () => config('cf_throw.key'))->toThrow(RuntimeException::class);
    } finally {
        remove_dir($dir);
    }
});

test('config throws when no default and key missing', function () {
    $dir = temp_dir('config_');
    file_put_contents($dir . '/cf_throw_key.php', '<?php return [];');

    try {
        Path::register('config', $dir);

        expect(fn () => config('cf_throw_key.missing'))->toThrow(RuntimeException::class);
    } finally {
        remove_file($dir . '/cf_throw_key.php');
        remove_dir($dir);
    }
});

test('config rejects invalid filenames even with a default', function (string $name) {
    $dir = temp_dir('config_');
    Path::register('config', $dir);

    try {
        expect(fn () => config($name, 'fallback'))->toThrow(RuntimeException::class);
    } finally {
        remove_dir($dir);
    }
})->with([
    'empty' => '',
    'traversal' => '../secret',
    'separator' => 'bad/name',
]);

test('config rejects a file outside the config directory', function () {
    $dir = temp_dir('config_');
    $outside = temp_dir('config_outside_');
    file_put_contents($outside . '/secret.php', '<?php return [];');
    symlink($outside . '/secret.php', $dir . '/linked.php');
    Path::register('config', $dir);

    try {
        expect(fn () => config('linked'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
        remove_tree($outside);
    }
});

test('config with single segment returns entire file array', function () {
    $dir = temp_dir('config_');
    file_put_contents($dir . '/cf_single.php', '<?php return ["key" => "value"];');

    try {
        Path::register('config', $dir);

        expect(config('cf_single'))->toBe(['key' => 'value']);
    } finally {
        remove_file($dir . '/cf_single.php');
        remove_dir($dir);
    }
});

test('config throws when file does not return array', function () {
    $dir = temp_dir('config_');
    file_put_contents($dir . '/cf_bad.php', '<?php return "string";');

    try {
        Path::register('config', $dir);

        expect(fn () => config('cf_bad'))->toThrow(RuntimeException::class);
    } finally {
        remove_file($dir . '/cf_bad.php');
        remove_dir($dir);
    }
});

test('config returns default when config path not registered', function () {
    expect(config('cf_nopath.key', 'fallback'))->toBe('fallback');
});

test('config throws when config path not registered and no default', function () {
    expect(fn () => config('cf_nopath_throw.key'))->toThrow(RuntimeException::class);
});

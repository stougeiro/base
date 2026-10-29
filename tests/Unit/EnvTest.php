<?php declare(strict_types=1);

use Sourcerer\Path;
use function Sourcerer\env;

beforeEach(fn () => reset_path());

function env_fixture(string $json): string
{
    $dir = temp_dir('env_');
    file_put_contents($dir . '/env.json', $json);
    Path::register('root', $dir);

    return $dir;
}

test('env reads dot-notated item from env.json', function () {
    $dir = env_fixture('{"environment":"testing","db":{"host":"localhost"}}');

    try {
        expect(env('environment'))->toBe('testing')
            ->and(env('db.host'))->toBe('localhost');
    } finally {
        remove_tree($dir);
    }
});

test('env throws when key is missing', function () {
    $dir = env_fixture('{}');

    try {
        expect(fn () => env('missing.key'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
});

test('env throws when traversing a scalar value', function () {
    $dir = env_fixture('{"environment":"testing"}');

    try {
        expect(fn () => env('environment.nested'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
});

test('env throws when file is missing', function () {
    $dir = temp_dir('env_');
    Path::register('root', $dir);

    try {
        expect(fn () => env('any.key'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
});

test('env throws when root path is not registered', function () {
    expect(fn () => env('any.key'))->toThrow(RuntimeException::class);
});

test('env rejects invalid JSON', function () {
    $dir = env_fixture('{"broken":');

    try {
        expect(fn () => env('broken'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
});

test('env rejects an empty JSON document', function (string $json) {
    $dir = env_fixture($json);

    try {
        expect(fn () => env('value'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
})->with([
    'empty' => '',
    'whitespace' => " \n\t",
]);

test('env rejects a root that is not a JSON object', function (string $json) {
    $dir = env_fixture($json);

    try {
        expect(fn () => env('value'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
})->with(['[]', '[1,2]', '42', '"value"', 'null', 'true']);

test('env accepts a file at the 64 KiB limit', function () {
    $json = '{"value":"ok"}';
    $dir = env_fixture($json . str_repeat(' ', (64 * 1024) - strlen($json)));

    try {
        expect(env('value'))->toBe('ok');
    } finally {
        remove_tree($dir);
    }
});

test('env rejects an oversized file', function () {
    $dir = env_fixture(str_repeat('x', (64 * 1024) + 1));

    try {
        expect(fn () => env('value'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
});

test('env rejects a symlinked file', function () {
    $dir = temp_dir('env_');
    $target = $dir . '/target.json';
    file_put_contents($target, '{"value":"linked"}');
    symlink($target, $dir . '/env.json');
    Path::register('root', $dir);

    try {
        expect(fn () => env('value'))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
});

test('env rejects invalid paths', function (string $path) {
    $dir = env_fixture('{"db":{"host":"localhost"}}');

    try {
        expect(fn () => env($path))->toThrow(RuntimeException::class);
    } finally {
        remove_tree($dir);
    }
})->with([
    'empty' => '',
    'empty segment' => 'db..host',
    'null byte' => "db\0host",
]);

test('env caches the first read', function () {
    $dir = env_fixture('{"value":"original"}');

    try {
        expect(env('value'))->toBe('original');

        file_put_contents($dir . '/env.json', '{"value":"changed"}');

        expect(env('value'))->toBe('original');
    } finally {
        remove_tree($dir);
    }
});

test('env does not populate superglobals or process environment', function () {
    $key = 'sourcerer_env_isolated';
    $dir = env_fixture(json_encode([$key => 'value'], JSON_THROW_ON_ERROR));

    try {
        expect(env($key))->toBe('value')
            ->and(isset($_ENV[$key]))->toBeFalse()
            ->and(isset($_SERVER[$key]))->toBeFalse()
            ->and(getenv($key))->toBeFalse();
    } finally {
        remove_tree($dir);
    }
});

test('env reads the canonical fixture in-process', function () {
    bootstrap_app();

    expect(env('environment'))->toBe('production')
        ->and(env('app.name'))->toBe('Sourcerer')
        ->and(env('app'))->toBe(['name' => 'Sourcerer']);
});

test('env throws for a missing key in-process', function () {
    bootstrap_app();

    expect(fn () => env('missing.key'))->toThrow(RuntimeException::class);
});

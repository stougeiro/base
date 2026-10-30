<?php declare(strict_types=1);

use Sourcerer\Path;
use Tests\Support\PerformanceHelper;

use function Sourcerer\env;

beforeEach(fn () => reset_path());

test('env cold reads stay under budget', function () {
    $dirs = [];

    for ($i = 0; $i < 20; $i++) {
        $dir = temp_dir('perf_env_');
        file_put_contents($dir . '/env.json', '{"value":"ok"}');
        $dirs[] = $dir;
    }

    try {
        $index = 0;

        $result = PerformanceHelper::benchmark(function () use (&$index, $dirs): void {
            reset_path();
            Path::register('root', $dirs[$index++]);
            env('value');
        }, 20, 0);

        expect($result['time'])->toBeLessThan(1.0);
    } finally {
        foreach ($dirs as $dir) {
            remove_tree($dir);
        }
    }
});

test('env 64 KiB documents stay under budget', function () {
    $dirs = [];
    $json = '{"value":"ok"}';
    $json .= str_repeat(' ', (64 * 1024) - strlen($json));

    for ($i = 0; $i < 8; $i++) {
        $dir = temp_dir('perf_env_large_');
        file_put_contents($dir . '/env.json', $json);
        $dirs[] = $dir;
    }

    try {
        $index = 0;

        $result = PerformanceHelper::benchmark(function () use (&$index, $dirs): void {
            reset_path();
            Path::register('root', $dirs[$index++]);
            env('value');
        }, 8, 0);

        expect($result['time'])->toBeLessThan(1.0);
    } finally {
        foreach ($dirs as $dir) {
            remove_tree($dir);
        }
    }
});

test('env warm lookups are cheap', function () {
    $dir = temp_dir('perf_env_warm_');
    file_put_contents($dir . '/env.json', '{"value":"ok"}');
    Path::register('root', $dir);
    env('value');

    try {
        $result = PerformanceHelper::benchmark(fn () => env('value'), 10_000, 100);

        expect($result['time'])->toBeLessThan(1.0);
    } finally {
        remove_tree($dir);
    }
});

test('env memory stays bounded at 64 KiB', function () {
    $dir = temp_dir('perf_env_memory_');
    $json = '{"value":"ok"}';
    $json .= str_repeat(' ', (64 * 1024) - strlen($json));
    file_put_contents($dir . '/env.json', $json);
    Path::register('root', $dir);

    try {
        $used = PerformanceHelper::memoryDelta(fn () => env('value'));

        expect($used)->toBeLessThan(2 * 1024 * 1024);
    } finally {
        remove_tree($dir);
    }
});

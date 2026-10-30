<?php declare(strict_types=1);

use Sourcerer\Log\Log;
use Tests\Support\PerformanceHelper;

test('log writes 1000 entries without context under budget', function () {
    $tempFile = sys_get_temp_dir() . '/perf_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $result = PerformanceHelper::benchmark(
            fn () => $log->info('Benchmark message'),
            1_000,
            10,
        );

        expect($result['time'])->toBeLessThan(2.0);
    } finally {
        remove_file($tempFile);
    }
});

test('log writes 1000 entries with context under budget', function () {
    $tempFile = sys_get_temp_dir() . '/perf_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $result = PerformanceHelper::benchmark(
            fn () => $log->error('Context message', ['key' => 'value', 'number' => 42]),
            1_000,
            10,
        );

        expect($result['time'])->toBeLessThan(2.0);
    } finally {
        remove_file($tempFile);
    }
});

test('log truncation path stays under budget', function () {
    $tempFile = sys_get_temp_dir() . '/perf_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $context = ['blob' => str_repeat('x', 9_000)];

        $result = PerformanceHelper::benchmark(
            fn () => $log->error(str_repeat('m', 5_000), $context),
            100,
            5,
        );

        expect($result['time'])->toBeLessThan(1.0);
    } finally {
        remove_file($tempFile);
    }
});

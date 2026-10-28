<?php declare(strict_types=1);

use Sourcerer\Log\Log;

test('log write performance benchmark', function () {
    $tempFile = sys_get_temp_dir() . '/perf_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $log->info("Benchmark message {$i}", ['iteration' => $i]);
        }

        $duration = microtime(true) - $start;

        expect($duration)->toBeLessThan(1.0)
            ->and(file_exists($tempFile))->toBeTrue();
    } finally {
        remove_file($tempFile);
    }
});

test('log writes 100 entries with and without context under one second', function () {
    $tempFile = sys_get_temp_dir() . '/perf_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $log->error('Simple error');
        }
        $durationNoContext = microtime(true) - $start;

        remove_file($tempFile);

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $log->error('Context error', ['key' => 'value', 'number' => $i]);
        }
        $durationWithContext = microtime(true) - $start;

        expect($durationNoContext)->toBeLessThan(1.0)
            ->and($durationWithContext)->toBeLessThan(1.0);
    } finally {
        remove_file($tempFile);
    }
});
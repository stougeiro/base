<?php declare(strict_types=1);

use Sourcerer\Log\Log;
use Tests\Support\PerformanceHelper;

test('log context truncation keeps memory bounded', function () {
    $tempFile = sys_get_temp_dir() . '/perf_memory_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $context = ['blob' => str_repeat('x', 9_000)];

        $used = PerformanceHelper::memoryDelta(
            fn () => $log->error(str_repeat('m', 5_000), $context),
        );

        expect($used)->toBeLessThan(2 * 1024 * 1024);
    } finally {
        remove_file($tempFile);
    }
});

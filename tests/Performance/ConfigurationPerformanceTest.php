<?php declare(strict_types=1);

use Sourcerer\Path;
use Tests\Support\PerformanceHelper;

use function Sourcerer\config;

beforeEach(fn () => reset_path());

test('config cold reads stay under budget', function () {
    $dir = temp_dir('perf_config_');
    $prefix = 'perf_' . uniqid() . '_';

    for ($i = 0; $i < 20; $i++) {
        file_put_contents(
            $dir . '/' . $prefix . $i . '.php',
            '<?php return ["value" => ' . $i . '];',
        );
    }

    Path::register('config', $dir);

    try {
        $index = 0;

        $result = PerformanceHelper::benchmark(
            function () use (&$index, $prefix): void {
                config($prefix . $index++ . '.value');
            },
            20,
            0,
        );

        expect($result['time'])->toBeLessThan(1.0);
    } finally {
        remove_tree($dir);
    }
});

test('config warm lookups are cheap', function () {
    $dir = temp_dir('perf_config_warm_');
    $prefix = 'perf_warm_' . uniqid();
    file_put_contents($dir . '/' . $prefix . '.php', '<?php return ["value" => 42];');
    Path::register('config', $dir);
    config($prefix . '.value');

    try {
        $result = PerformanceHelper::benchmark(fn () => config($prefix . '.value'), 10_000, 100);

        expect($result['time'])->toBeLessThan(1.0);
    } finally {
        remove_tree($dir);
    }
});

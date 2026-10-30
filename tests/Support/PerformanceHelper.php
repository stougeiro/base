<?php declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;

final class PerformanceHelper
{
    /**
     * @return array{time: float, iterations: int, per_op_us: float, ops_per_sec: float}
     */
    public static function benchmark(callable $fn, int $iterations, int $warmup = 10): array
    {
        if ($iterations < 1) {
            throw new InvalidArgumentException('Iterations must be greater than zero');
        }

        for ($i = 0; $i < $warmup; $i++) {
            $fn();
        }

        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $fn();
        }

        $elapsed = (hrtime(true) - $start) / 1e9;

        return [
            'time' => $elapsed,
            'iterations' => $iterations,
            'per_op_us' => round(($elapsed / $iterations) * 1e6, 4),
            'ops_per_sec' => round($iterations / $elapsed, 0),
        ];
    }

    public static function memoryDelta(callable $fn): int
    {
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }

        $before = memory_get_usage();
        $fn();

        return memory_get_usage() - $before;
    }

    /**
     * @param array{time: float, iterations: int, per_op_us: float, ops_per_sec: float} $result
     */
    public static function format(array $result): string
    {
        return sprintf(
            '%d ops in %.4fs (%.4f us/op, %s ops/sec)',
            $result['iterations'],
            $result['time'],
            $result['per_op_us'],
            number_format($result['ops_per_sec']),
        );
    }
}

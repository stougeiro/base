<?php declare(strict_types=1);

use Sourcerer\Error\Fallback;

test('fallback builds a throwable from a fatal last error', function () {
    $error = Fallback::fromLastError([
        'type' => E_ERROR,
        'message' => 'boom',
        'file' => __FILE__,
        'line' => 10,
    ]);

    expect($error)->toBeInstanceOf(ErrorException::class)
        ->and($error->getMessage())->toBe('boom')
        ->and($error->getFile())->toBe(__FILE__)
        ->and($error->getLine())->toBe(10)
        ->and($error->getSeverity())->toBe(E_ERROR);
});

test('fallback ignores non-fatal last errors', function () {
    expect(Fallback::fromLastError(['type' => E_WARNING]))->toBeNull()
        ->and(Fallback::fromLastError([]))->toBeNull()
        ->and(Fallback::fromLastError(['type' => 'E_ERROR']))->toBeNull();
});

test('fallback defaults malformed last error fields', function () {
    $error = Fallback::fromLastError([
        'type' => E_ERROR,
        'message' => 123,
        'file' => [],
        'line' => 'nope',
    ]);

    expect($error)->not->toBeNull()
        ->and($error->getMessage())->toBe('')
        ->and($error->getFile())->toBe('')
        ->and($error->getLine())->toBe(0);
});

test('fallback shutdown guard emits a single document on a fatal error', function () {
    $output = run_php(
        '\Sourcerer\Error\Fallback::register();'
        . '\Sourcerer\Error\Fallback::register();'
        . 'undefined_function();',
    );

    expect($output)->toContain('500 Internal Server Error')
        ->and(substr_count($output, '<!DOCTYPE html>'))->toBe(1)
        ->and($output)->toContain('Reference:');
});

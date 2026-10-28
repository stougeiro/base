<?php declare(strict_types=1);

use Sourcerer\Service\Result;

test('success result returns success status and data', function () {
    $result = Result::success(['foo' => 'bar'], ['Success message']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->messages())->toBe(['Success message'])
        ->and($result->data()->foo)->toBe('bar');
});

test('failure result returns failure status and data', function () {
    $result = Result::failure(['error' => 'failed'], ['Error message']);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->messages())->toBe(['Error message'])
        ->and($result->data()->error)->toBe('failed');
});

test('result with null data works correctly', function () {
    $result = Result::success();

    expect($result->isSuccess())->toBeTrue()
        ->and($result->data())->toBeNull();
});

test('result with empty data array', function () {
    $result = Result::success([]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->data())->not->toBeNull();
});

test('result with multiple messages', function () {
    $result = Result::failure(['key' => 'val'], ['First error', 'Second error']);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->messages())->toHaveCount(2)
        ->and($result->messages()[0])->toBe('First error')
        ->and($result->messages()[1])->toBe('Second error');
});

test('success result with no messages defaults to empty array', function () {
    $result = Result::success(['ok' => true]);

    expect($result->messages())->toBe([]);
});
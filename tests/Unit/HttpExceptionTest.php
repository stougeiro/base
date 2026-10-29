<?php declare(strict_types=1);

use Sourcerer\Error\Exception\HttpException;

test('http exception with valid status 400', function () {
    $e = new HttpException(400);

    expect($e->status())->toBe(400)
        ->and($e->headers())->toBe([]);
});

test('http exception with valid status 599', function () {
    $e = new HttpException(599);

    expect($e->status())->toBe(599);
});

test('http exception with custom message and headers', function () {
    $e = new HttpException(404, 'Not Found', ['Allow' => 'GET, POST']);

    expect($e->getMessage())->toBe('Not Found')
        ->and($e->headers())->toBe(['Allow' => 'GET, POST'])
        ->and($e->getCode())->toBe(404);
});

test('http exception with previous throwable', function () {
    $previous = new RuntimeException('Previous error');
    $e = new HttpException(500, '', [], $previous);

    expect($e->getPrevious())->toBe($previous);
});

test('http exception rejects invalid headers', function (array $headers) {
    expect(fn () => new HttpException(400, '', $headers))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'non-string value' => [['X-Test' => ['bad']]],
    'invalid name' => [['Bad Header' => 'value']],
    'empty name' => [['' => 'value']],
    'control character' => [['X-Test' => "bad\r\nvalue"]],
    'oversized value' => [['X-Test' => str_repeat('a', 8193)]],
]);

test('http exception throws on status below 400', function () {
    expect(fn () => new HttpException(399))->toThrow(InvalidArgumentException::class);
});

test('http exception throws on status above 599', function () {
    expect(fn () => new HttpException(600))->toThrow(InvalidArgumentException::class);
});

test('http exception throws on status 0', function () {
    expect(fn () => new HttpException(0))->toThrow(InvalidArgumentException::class);
});
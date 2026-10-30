<?php declare(strict_types=1);

use Sourcerer\Error\ErrorHandler;
use Sourcerer\Error\Exception\HttpException;
use Sourcerer\Log\Log;
use Sourcerer\Log\LogInterface;
use STDW\Http\Response;
use Tests\Fixtures\FakeRequest;
use Tests\Support\PerformanceHelper;

beforeEach(function () {
    bootstrap_app();
    reset_container();

    $this->logDir = temp_dir('perf_error_');
    container()->singleton(
        LogInterface::class,
        fn () => new Log($this->logDir . '/app.log'),
    );

    $this->handler = new ErrorHandler(container());
});

afterEach(function () {
    remove_tree($this->logDir);
});

test('production 4xx handling stays under budget', function () {
    $error = new HttpException(404, 'Not Found');
    $request = new FakeRequest('GET', 'http://localhost/missing');

    $result = PerformanceHelper::benchmark(
        fn () => $this->handler->handle($error, $request, new Response()),
        500,
        10,
    );

    expect($result['time'])->toBeLessThan(2.0);
});

test('production 5xx handling stays under budget', function () {
    $error = new RuntimeException('boom');
    $request = new FakeRequest('GET', 'http://localhost/boom');

    $result = PerformanceHelper::benchmark(
        fn () => $this->handler->handle($error, $request, new Response()),
        500,
        10,
    );

    expect($result['time'])->toBeLessThan(2.0);
});

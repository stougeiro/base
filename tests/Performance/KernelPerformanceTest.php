<?php declare(strict_types=1);

use Sourcerer\Http\Kernel;
use Sourcerer\Path;
use STDW\Contract\Http\RequestHandlerInterface;
use Tests\Fixtures\FakeRequest;
use Tests\Fixtures\Performance\NoopHandler;
use Tests\Support\PerformanceHelper;

test('cold kernel cycles stay under budget', function () {
    $root = fixture_root();

    $result = PerformanceHelper::benchmark(function () use ($root): void {
        reset_path();
        Path::register('root', $root);
        Path::register('config', $root . '/config');
        Path::register('logs', $root . '/logs');

        reset_container();
        reset_kernel();

        $kernel = Kernel::create();
        container()->singleton(RequestHandlerInterface::class, fn () => new NoopHandler());

        $kernel->handle(new FakeRequest('GET', 'http://localhost/health'));
        $kernel->terminate();
    }, 10, 1);

    expect($result['time'])->toBeLessThan(2.0);
});

test('warm kernel dispatch stays under budget', function () {
    bootstrap_app();
    reset_container();
    reset_kernel();

    $kernel = Kernel::create();
    container()->singleton(RequestHandlerInterface::class, fn () => new NoopHandler());
    $kernel->handle(new FakeRequest('GET', 'http://localhost/health'));

    $result = PerformanceHelper::benchmark(
        fn () => $kernel->handle(new FakeRequest('GET', 'http://localhost/health')),
        1_000,
        10,
    );

    expect($result['time'])->toBeLessThan(2.0);
});

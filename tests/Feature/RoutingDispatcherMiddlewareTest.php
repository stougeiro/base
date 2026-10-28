<?php declare(strict_types=1);

use Sourcerer\Error\Exception\HttpException;
use Sourcerer\Http\RoutingDispatcherMiddleware;
use Sourcerer\Http\RoutingMiddlewareCollection;
use STDW\Container\Container;
use STDW\Contract\Http\Router\RouterInterface;
use STDW\Http\Response;
use STDW\Http\Router\RouteCollection;
use STDW\Http\Router\Router;
use Tests\Fixtures\ArrayCache;
use Tests\Fixtures\FakeRequest;
use Tests\Fixtures\Provider;

beforeEach(function () {
    $this->container = new Container();
    $this->middlewares = new RoutingMiddlewareCollection($this->container);
    $this->dispatcher = new RoutingDispatcherMiddleware($this->container, $this->middlewares);
});

function bind_router(Container $container, string $routes): void
{
    $cache = new ArrayCache();
    $collection = new RouteCollection($cache);
    $collection->load($routes);

    $container->bind(RouterInterface::class, fn () => new Router($collection, $cache));
}

test('routing dispatcher returns 404 when route is not matched', function () {
    bind_router($this->container, __DIR__ . '/../Fixtures/routes/plain.php');

    $error = null;

    try {
        $this->dispatcher->process(
            new FakeRequest('GET', 'http://localhost/missing'),
            new Response(),
            fn ($request, $response) => $response,
        );
    } catch (HttpException $exception) {
        $error = $exception;
    }

    expect($error)->toBeInstanceOf(HttpException::class)
        ->and($error->status())->toBe(404);
});

test('routing dispatcher runs the matched controller with the route variables', function () {
    bind_router($this->container, __DIR__ . '/../Fixtures/routes/dispatch.php');

    $result = $this->dispatcher->process(
        new FakeRequest('GET', 'http://localhost/users/42'),
        new Response(),
        fn ($request, $response) => $response,
    );

    expect($result->getStatus())->toBe(200)
        ->and($result->getBody())->toBe('user:42');
});

test('routing dispatcher runs the module middlewares before the controller', function () {
    $this->middlewares->load(Provider::class, __DIR__ . '/../Fixtures/middlewares/dispatch.php');
    bind_router($this->container, __DIR__ . '/../Fixtures/routes/dispatch.php');

    $result = $this->dispatcher->process(
        new FakeRequest('GET', 'http://localhost/users/42'),
        new Response(),
        fn ($request, $response) => $response,
    );

    expect($result->getHeader('X-Passed'))->toBe('yes')
        ->and($result->getBody())->toBe('user:42');
});

test('routing dispatcher rejects a route pointing to a non controller', function () {
    bind_router($this->container, __DIR__ . '/../Fixtures/routes/bad.php');

    expect(fn () => $this->dispatcher->process(
        new FakeRequest('GET', 'http://localhost/bad'),
        new Response(),
        fn ($request, $response) => $response,
    ))->toThrow(RuntimeException::class);
});

test('routing dispatcher raises 405 with the allow header for a rejected verb', function () {
    bind_router($this->container, __DIR__ . '/../Fixtures/routes/dispatch.php');

    $error = null;

    try {
        $this->dispatcher->process(
            new FakeRequest('POST', 'http://localhost/users/42'),
            new Response(),
            fn ($request, $response) => $response,
        );
    } catch (HttpException $exception) {
        $error = $exception;
    }

    expect($error)->toBeInstanceOf(HttpException::class)
        ->and($error->status())->toBe(405)
        ->and($error->headers())->toBe(['Allow' => 'GET, OPTIONS']);
});

test('routing dispatcher answers options through the controller', function () {
    bind_router($this->container, __DIR__ . '/../Fixtures/routes/dispatch.php');

    $result = $this->dispatcher->process(
        new FakeRequest('OPTIONS', 'http://localhost/users/42'),
        new Response(),
        fn ($request, $response) => $response,
    );

    expect($result->getStatus())->toBe(204)
        ->and($result->getHeader('Allow'))->toBe('GET, OPTIONS');
});

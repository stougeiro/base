<?php declare(strict_types=1);

use Sourcerer\Http\RoutingMiddlewareCollection;
use STDW\Container\Container;
use STDW\Contract\Http\Router\RouteCollectionInterface;
use STDW\Http\Router\RouteCollection;
use Tests\Fixtures\ArrayCache;
use Tests\Fixtures\OkController;
use Tests\Fixtures\PassMiddleware;
use Tests\Fixtures\Provider;

beforeEach(fn () => reset_path());

test('module provider registers routes through the collection', function () {
    $container = new Container();
    $container->singleton(RouteCollectionInterface::class, fn () => new RouteCollection(new ArrayCache()));

    $provider = new Provider($container);
    $provider->registerRoutes(__DIR__ . '/../Fixtures/routes/provider.php');

    $routes = $container->get(RouteCollectionInterface::class);

    expect($routes->all()['names']['health'])->toBe('health');
});

test('module provider registers middlewares through the collection', function () {
    $container = new Container();
    $container->singleton(
        RoutingMiddlewareCollection::class,
        fn () => new RoutingMiddlewareCollection($container),
    );

    $provider = new Provider($container);
    $provider->registerMiddlewares(__DIR__ . '/../Fixtures/middlewares/provider.php');

    $middlewares = $container->get(RoutingMiddlewareCollection::class);
    $resolved = $middlewares->forController(OkController::class);

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])->toBeInstanceOf(PassMiddleware::class);
});

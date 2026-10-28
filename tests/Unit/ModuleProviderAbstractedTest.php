<?php declare(strict_types=1);

use Sourcerer\Http\RoutingMiddlewareCollection;
use STDW\Container\Container;
use STDW\Contract\Http\Router\RouteCollectionInterface;
use STDW\Contract\View\AliasManagerInterface;
use STDW\Http\Router\RouteCollection;
use Tests\Fixtures\ArrayCache;
use Tests\Fixtures\FakeAliasManager;
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

test('module provider registers view aliases through the manager', function () {
    $container = new Container();
    $container->singleton(AliasManagerInterface::class, fn () => new FakeAliasManager());

    $provider = new Provider($container);
    $provider->registerViews('admin', '/tmp/views');

    $aliases = $container->get(AliasManagerInterface::class);

    expect($aliases->alias('admin'))->toBe('/tmp/views/')
        ->and($aliases->resolveAlias('admin:home'))->toBe('/tmp/views/home');
});

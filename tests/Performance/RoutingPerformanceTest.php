<?php declare(strict_types=1);

use Sourcerer\Http\RoutingMiddlewareCollection;
use STDW\Container\Container;
use STDW\Http\Response;
use Tests\Fixtures\FakeRequest;
use Tests\Fixtures\OkController;
use Tests\Fixtures\PassMiddleware;
use Tests\Fixtures\TestModule;
use Tests\Support\PerformanceHelper;

function middleware_aliases(int $count): array
{
    $classes = [];

    for ($i = 0; $i < $count; $i++) {
        $alias = 'Tests\\Fixtures\\Performance\\PerfMiddleware' . $i;

        if ( ! class_exists($alias, false)) {
            class_alias(PassMiddleware::class, $alias);
        }

        $classes[] = $alias;
    }

    return $classes;
}

test('middleware resolution scales predictably', function (int $count, int $iterations) {
    $file = temp_php('<?php return ["*" => ' . var_export(middleware_aliases($count), true) . '];');
    $collection = new RoutingMiddlewareCollection(new Container());
    $collection->load(TestModule::class, $file);

    try {
        $result = PerformanceHelper::benchmark(
            fn () => $collection->forController(OkController::class),
            $iterations,
            5,
        );

        expect($result['time'])->toBeLessThan(2.0);
    } finally {
        remove_file($file);
    }
})->with([
    '1 middleware' => [1, 1_000],
    '10 middleware' => [10, 500],
    '100 middleware' => [100, 200],
]);

test('controller dispatch stays under budget', function () {
    $request = (new FakeRequest('GET', 'http://localhost/users/42'))->withAttribute('id', '42');
    $controller = new OkController();
    $response = new Response();
    $next = static fn () => $response;

    $result = PerformanceHelper::benchmark(
        fn () => $controller->process($request, $response, $next),
        10_000,
        100,
    );

    expect($result['time'])->toBeLessThan(1.0);
});

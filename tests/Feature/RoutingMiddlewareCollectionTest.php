<?php declare(strict_types=1);

use Sourcerer\Http\RoutingMiddlewareCollection;
use STDW\Container\Container;
use Tests\Fixtures\Deep\DeepController;
use Tests\Fixtures\Deep\DeepMiddleware;
use Tests\Fixtures\Deep\DeepProvider;
use Tests\Fixtures\OkController;
use Tests\Fixtures\PassMiddleware;
use Tests\Fixtures\Provider;

beforeEach(function () {
    $this->container = new Container();
    $this->collection = new RoutingMiddlewareCollection($this->container);
});

function php_class(string $fqcn): string
{
    return '\\' . $fqcn . '::class';
}

test('load stores a valid middleware map', function () {
    $file = temp_php('<?php return ["*" => [' . php_class(PassMiddleware::class) . ']];');

    try {
        $this->collection->load(Provider::class, $file);

        expect($this->collection->forController(OkController::class))->toHaveCount(1);
    } finally {
        remove_file($file);
    }
});

test('load throws on missing file', function () {
    expect(fn () => $this->collection->load(Provider::class, '/nonexistent/file.php'))
        ->toThrow(RuntimeException::class);
});

test('load throws on non-array return', function () {
    $file = temp_php('<?php return "string";');

    try {
        expect(fn () => $this->collection->load(Provider::class, $file))
            ->toThrow(RuntimeException::class);
    } finally {
        remove_file($file);
    }
});

test('load throws on non-array value', function () {
    $file = temp_php('<?php return ["*" => "not_an_array"];');

    try {
        expect(fn () => $this->collection->load(Provider::class, $file))
            ->toThrow(RuntimeException::class);
    } finally {
        remove_file($file);
    }
});

test('load throws on non-middleware class', function () {
    $file = temp_php('<?php return ["*" => [' . php_class(stdClass::class) . ']];');

    try {
        expect(fn () => $this->collection->load(Provider::class, $file))
            ->toThrow(RuntimeException::class);
    } finally {
        remove_file($file);
    }
});

test('load throws on a key that is neither a wildcard nor a controller of the module', function () {
    $file = temp_php('<?php return ["invalid-key" => [' . php_class(PassMiddleware::class) . ']];');

    try {
        expect(fn () => $this->collection->load(Provider::class, $file))
            ->toThrow(RuntimeException::class);
    } finally {
        remove_file($file);
    }
});

test('load throws on a module without namespace', function () {
    $file = temp_php('<?php return ["*" => []];');

    try {
        expect(fn () => $this->collection->load('NoNamespaceModule', $file))
            ->toThrow(RuntimeException::class);
    } finally {
        remove_file($file);
    }
});

test('forController returns empty array when no module is loaded', function () {
    expect($this->collection->forController(OkController::class))->toBe([]);
});

test('forController puts the module wildcard before the controller middlewares', function () {
    $file = temp_php('<?php return ["*" => [' . php_class(PassMiddleware::class) . '], '
        . php_class(OkController::class) . ' => [' . php_class(DeepMiddleware::class) . ']];');

    try {
        $this->collection->load(Provider::class, $file);

        $resolved = $this->collection->forController(OkController::class);

        expect($resolved)->toHaveCount(2)
            ->and($resolved[0])->toBeInstanceOf(PassMiddleware::class)
            ->and($resolved[1])->toBeInstanceOf(DeepMiddleware::class);
    } finally {
        remove_file($file);
    }
});

test('forController uses the longest matching module namespace', function () {
    $root = temp_php('<?php return ["*" => [' . php_class(PassMiddleware::class) . ']];');
    $deep = temp_php('<?php return ["*" => [' . php_class(DeepMiddleware::class) . ']];');

    try {
        $this->collection->load(Provider::class, $root);
        $this->collection->load(DeepProvider::class, $deep);

        expect($this->collection->forController(DeepController::class)[0])
            ->toBeInstanceOf(DeepMiddleware::class)
            ->and($this->collection->forController(OkController::class)[0])
            ->toBeInstanceOf(PassMiddleware::class);
    } finally {
        remove_file($root);
        remove_file($deep);
    }
});

test('forController deduplicates repeated middleware classes', function () {
    $file = temp_php('<?php return ["*" => ['
        . php_class(PassMiddleware::class) . ', ' . php_class(PassMiddleware::class) . ']];');

    try {
        $this->collection->load(Provider::class, $file);

        expect($this->collection->forController(OkController::class))->toHaveCount(1);
    } finally {
        remove_file($file);
    }
});

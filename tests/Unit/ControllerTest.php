<?php declare(strict_types=1);

use Sourcerer\Error\Exception\HttpException;
use STDW\Http\Response;
use Tests\Fixtures\FakeRequest;
use Tests\Fixtures\OkController;

test('controller answers options with 204 and allow header', function () {
    $controller = new OkController();
    $accumulated = (new Response())->withHeader('X-Base', '1')->withBody('ignored');

    $result = $controller->process(
        new FakeRequest('OPTIONS', 'http://localhost/users/1'),
        $accumulated,
        fn ($request, $response) => $response,
    );

    expect($result->getStatus())->toBe(204)
        ->and($result->getHeader('Allow'))->toBe('GET, OPTIONS')
        ->and($result->getHeader('X-Base'))->toBe('1')
        ->and($result->getBody())->toBeNull();
});

test('controller dispatches an allowed verb to its handler', function () {
    $request = new FakeRequest('GET', 'http://localhost/users/42');
    $request->withAttribute('id', '42');

    $result = (new OkController())->process(
        $request,
        new Response(),
        fn ($req, $res) => $res,
    );

    expect($result->getStatus())->toBe(200)
        ->and($result->getBody())->toBe('user:42');
});

test('controller rejects a verb outside the allowed list', function () {
    $error = null;

    try {
        (new OkController())->process(
            new FakeRequest('POST', 'http://localhost/users/42'),
            new Response(),
            fn ($req, $res) => $res,
        );
    } catch (HttpException $exception) {
        $error = $exception;
    }

    expect($error)->toBeInstanceOf(HttpException::class)
        ->and($error->status())->toBe(405)
        ->and($error->headers())->toBe(['Allow' => 'GET, OPTIONS']);
});

<?php declare(strict_types=1);

namespace Tests\Fixtures\Performance;

use STDW\Contract\Http\RequestHandlerInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;

final class NoopHandler implements RequestHandlerInterface
{
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response->withBody('ok');
    }
}

<?php declare(strict_types=1);

namespace Tests\Fixtures\Deep;

use STDW\Contract\Http\MiddlewareInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;

use Closure;


final class DeepMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, ResponseInterface $response, Closure $next): ResponseInterface
    {
        return $next($request, $response->withHeader('X-Deep', 'yes'));
    }
}

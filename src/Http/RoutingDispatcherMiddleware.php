<?php declare(strict_types=1);

namespace Sourcerer\Http;

use STDW\Contract\Container\ContainerInterface;
use STDW\Contract\Http\MiddlewareInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;
use STDW\Contract\Http\Router\RouterInterface;
use STDW\Http\Middleware\MiddlewareManager;

use Sourcerer\Error\Exception\HttpException;
use Sourcerer\Http\Controller\ControllerInterface;

use Closure;
use RuntimeException;


/**
 * Final middleware of the application queue: it matches the route, feeds the
 * route variables into the request and runs the route chain (module and
 * controller middlewares) over the accumulated response, the controller being
 * the chain's final handler.
 */
final class RoutingDispatcherMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly RoutingMiddlewareCollection $middlewares,
    ) { }


    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param Closure $next
     * @return ResponseInterface
     */
    public function process(RequestInterface $request, ResponseInterface $response, Closure $next): ResponseInterface
    {
        /** @var RouterInterface $router */
        $router = $this->container->get(RouterInterface::class);

        $route = $router->match($request);

        if (is_null($route)) {
            throw new HttpException(404, 'Not Found');
        }

        foreach ($route->getVariables() as $name => $value) {
            $request->withAttribute($name, $value);
        }

        /** @var class-string<ControllerInterface> $controller */
        $controller = $route->getController();

        if ( ! is_subclass_of($controller, ControllerInterface::class)) {
            throw new RuntimeException("route: controller '{$controller}' is not a controller");
        }

        $manager = new MiddlewareManager();

        foreach ($this->middlewares->forController($controller) as $middleware) {
            $manager->add($middleware);
        }

        /** @var ControllerInterface $handler */
        $handler = $this->container->make($controller);

        $manager->setFinalHandler($handler);

        return $manager->handle($request, $response);
    }
}

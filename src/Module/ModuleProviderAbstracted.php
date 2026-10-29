<?php declare(strict_types=1);

namespace Sourcerer\Module;

use STDW\Contract\Container\ContainerInterface;
use STDW\Contract\Http\Router\RouteCollectionInterface;

use Sourcerer\Http\RoutingMiddlewareCollection;


/**
 * Base for application modules. The constructor only stores the container;
 * configure() runs after all services have been registered and booted.
 */
abstract class ModuleProviderAbstracted implements ModuleProviderInterface
{
    final public function __construct(
        protected readonly ContainerInterface $container,
    ) { }


    abstract public function configure(): void;


    /**
     * Registers the routes of this module.
     */
    protected function routes(string $file): void
    {
        /** @var RouteCollectionInterface $routes */
        $routes = $this->container->get(RouteCollectionInterface::class);

        $routes->load($file);
    }

    /**
     * Registers the middlewares of this module.
     *
     * The file returns a map keyed by '*' (the whole module) or by a
     * controller FQCN (a single controller).
     */
    protected function middlewares(string $file): void
    {
        /** @var RoutingMiddlewareCollection $middlewares */
        $middlewares = $this->container->get(RoutingMiddlewareCollection::class);

        $middlewares->load(static::class, $file);
    }
}

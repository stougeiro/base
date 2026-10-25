<?php declare(strict_types=1);

namespace Sourcerer\Module;

use STDW\Contract\Container\ContainerInterface;
use STDW\Contract\View\AliasManagerInterface;
use STDW\Contract\Http\Router\RouteCollectionInterface;

use Sourcerer\Http\RoutingMiddlewareCollection;


/**
 * Base for application modules: it receives a container whose services are
 * already registered and booted, and only configures the module's surface.
 */
abstract class ModuleProviderAbstracted implements ModuleProviderInterface
{
    final public function __construct(
        protected readonly ContainerInterface $container,
    ) { }


    /**
     * @return void
     */
    abstract public function configure(): void;


    /**
     * Registers the routes of this module.
     * 
     * @param string $file 
     * @return void 
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
     *
     * @param string $file
     * @return void
     */
    protected function middlewares(string $file): void
    {
        /** @var RoutingMiddlewareCollection $middlewares */
        $middlewares = $this->container->get(RoutingMiddlewareCollection::class);

        $middlewares->load(static::class, $file);
    }

    /**
     * Registers a view namespace for this module.
     *
     * @param string $alias
     * @param string $path
     * @return void
     */
    protected function views(string $alias, string $path): void
    {
        /** @var AliasManagerInterface $views */
        $views = $this->container->get(AliasManagerInterface::class);

        $views->alias($alias, $path);
    }
}

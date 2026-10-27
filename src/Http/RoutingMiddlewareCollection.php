<?php declare(strict_types=1);

namespace Sourcerer\Http;

use STDW\Contract\Container\ContainerInterface;
use STDW\Contract\Http\MiddlewareInterface;

use Sourcerer\Module\ModuleProviderInterface;
use Sourcerer\Http\Controller\ControllerInterface;

use RuntimeException;


/**
 * Middlewares of the application by module scope: the module level ('*') and
 * the controller level (the exact controller key), declared by each module in
 * its Config/middlewares.php.
 */
final class RoutingMiddlewareCollection
{
    /**
     * Module namespace => the module's middleware map.
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $modules = [];


    public function __construct(
        private readonly ContainerInterface $container,
    ) { }


    /**
     * Registers the middleware map of a module.
     *
     * The map keys are '*' (the whole module) or a controller FQCN of the
     * module; the values are lists of middleware classes.
     *
     * @param class-string<ModuleProviderInterface> $module The module provider class.
     * @param string $file The file returning the map.
     * @return void
     */
    public function load(string $module, string $file): void
    {
        $path = realpath($file);

        if ($path === false) {
            throw new RuntimeException("middlewares: file '{$file}' not found");
        }

        /** @var mixed $map */
        $map = include $path;

        if ( ! is_array($map)) {
            throw new RuntimeException("middlewares: file '{$file}' must return an array");
        }

        $namespace = $this->namespaceOf($module);

        /** @var array<int|string, mixed> $map */
        foreach ($map as $key => $list) {
            $owned = is_string($key)
                && str_starts_with($key, $namespace)
                && is_subclass_of($key, ControllerInterface::class);

            if ($key !== '*' && ! $owned) {
                throw new RuntimeException("middlewares: invalid key '{$key}', expected '*' or a controller of '{$namespace}'");
            }

            if ( ! is_array($list)) {
                throw new RuntimeException("middlewares: the value of '{$key}' must be an array");
            }

            /** @var array<mixed> $list */
            foreach ($list as $class) {
                if ( ! is_string($class) || ! is_subclass_of($class, MiddlewareInterface::class)) {
                    throw new RuntimeException("middlewares: the list of '{$key}' must hold middleware class names");
                }
            }
        }

        /** @var array<string, list<string>> $map */
        $this->modules[$namespace] = $map;
    }


    /**
     * The module and controller level middlewares of a controller, ready to
     * run: the module ones ('*') come before the controller ones.
     *
     * @param string $controller
     * @return array<int, MiddlewareInterface>
     */
    public function forController(string $controller): array
    {
        $map = $this->mapOf($controller);

        return $this->resolve([...($map['*'] ?? []), ...($map[$controller] ?? [])]);
    }


    /**
     * The map of the module that owns the controller; the longest namespace
     * wins when more than one matches.
     *
     * @param string $controller
     * @return array<string, list<string>>
     */
    private function mapOf(string $controller): array
    {
        $map = [];
        $length = 0;

        foreach ($this->modules as $namespace => $candidate) {
            if (str_starts_with($controller, $namespace) && strlen($namespace) > $length) {
                $map = $candidate;
                $length = strlen($namespace);
            }
        }

        return $map;
    }

    /**
     * @param class-string $module
     * @return string
     */
    private function namespaceOf(string $module): string
    {
        $position = strrpos($module, '\\');

        if ($position === false) {
            throw new RuntimeException("middlewares: module '{$module}' has no namespace");
        }

        return substr($module, 0, $position + 1);
    }

    /**
     * @param list<string> $classes
     * @return list<MiddlewareInterface>
     */
    private function resolve(array $classes): array
    {
        $middlewares = [];

        foreach (array_unique($classes) as $class) {
            /** @var MiddlewareInterface $middleware */
            $middleware = $this->container->make($class);

            $middlewares[] = $middleware;
        }

        return $middlewares;
    }
}

<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Sourcerer\Module\ModuleProviderAbstracted;


final class Provider extends ModuleProviderAbstracted
{
    public function configure(): void
    { }


    public function registerRoutes(string $file): void
    {
        $this->routes($file);
    }

    public function registerMiddlewares(string $file): void
    {
        $this->middlewares($file);
    }

    public function registerViews(string $alias, string $path): void
    {
        $this->views($alias, $path);
    }
}

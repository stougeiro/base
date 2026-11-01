<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Sourcerer\Contract\Module\ModuleProviderAbstract;


final class Provider extends ModuleProviderAbstract
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
}

<?php declare(strict_types=1);

namespace Tests\Fixtures;

use STDW\Contract\Container\ContainerInterface;
use STDW\Contract\Container\ServiceProviderInterface;


final class TestProvider implements ServiceProviderInterface
{
    public static int $registered = 0;
    public static int $booted = 0;
    public static int $terminated = 0;


    public function __construct(
        private readonly ContainerInterface $container,
    ) { }


    public function register(): void
    {
        self::$registered++;
    }

    public function boot(): void
    {
        self::$booted++;
    }

    public function terminate(): void
    {
        self::$terminated++;
    }
}

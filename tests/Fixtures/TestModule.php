<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Sourcerer\Module\ModuleProviderAbstracted;


final class TestModule extends ModuleProviderAbstracted
{
    public static int $configured = 0;


    public function configure(): void
    {
        self::$configured++;
    }
}

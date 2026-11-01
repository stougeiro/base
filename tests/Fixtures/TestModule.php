<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Sourcerer\Contract\Module\ModuleProviderAbstract;


final class TestModule extends ModuleProviderAbstract
{
    public static int $configured = 0;


    public function configure(): void
    {
        self::$configured++;
    }
}

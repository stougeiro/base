<?php declare(strict_types=1);

namespace Sourcerer\Module;


/**
 * Application-level provider. It never registers services — it configures the
 * module over an application whose services are already registered and booted.
 */
interface ModuleProviderInterface
{
    /**
     * @return void
     */
    public function configure(): void;
}

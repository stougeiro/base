<?php declare(strict_types=1);

namespace Sourcerer\Http;

use STDW\Container\ServiceManager;
use STDW\Contract\Container\ContainerInterface;
use STDW\Contract\Container\ServiceManagerInterface;
use STDW\Contract\Container\ServiceProviderInterface;
use STDW\Contract\Http\RequestHandlerInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;
use STDW\Http\Response;

use Sourcerer\Error\ErrorHandler;
use Sourcerer\Error\Fallback;
use Sourcerer\Module\ModuleProviderInterface;
use function Sourcerer\config;
use function Sourcerer\env;

use Throwable;


/**
 * Application lifecycle: services register and boot first, and only then the
 * modules configure themselves over the already configured application.
 *
 * The kernel is built once per process and boots once, no matter how many
 * requests it handles.
 *
 * It reads configuration through the global helpers and resolves services
 * from the process-wide container, so the kernel, container, config, env and
 * paths are scoped to the FPM worker and shared by every request it handles.
 * Long-running runtimes that serve concurrent requests are not supported.
 *
 * Error reporting is secured before configuration: the fatal fallback and the
 * production-safe ini are installed first, and development mode is enabled
 * only after the environment is known.
 */
final class Kernel
{
    private static ?self $instance = null;

    private bool $booted = false;


    /** @param list<ModuleProviderInterface> $modules 
     */
    private function __construct(
        private ContainerInterface $container,
        private ServiceManagerInterface $manager,
        private array $modules
    ) { }


    public static function create(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        Fallback::register();
        ErrorHandler::harden();

        if (env('environment', 'production') === 'development') {
            ErrorHandler::register('development');
            ErrorHandler::harden(true);
        }

        $container = container();
        $container->singleton(ServiceManagerInterface::class, ServiceManager::class);

        /** @var ServiceManagerInterface $manager */
        $manager = $container->get(ServiceManagerInterface::class);

        /** @var list<class-string<ServiceProviderInterface>> $providers */
        $providers = config('app.providers', []);

        foreach ($providers as $provider) {
            $manager->add(new $provider($container));
        }

        /** @var list<class-string<ModuleProviderInterface>> $modules */
        $modules = config('app.modules', []);
        $instances = [];

        foreach ($modules as $module) {
            $instances[] = new $module($container);
        }

        return self::$instance = new self($container, $manager, $instances);
    }


    public function handle(RequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $this->boot();

            /** @var RequestHandlerInterface $handler */
            $handler = $this->container->get(RequestHandlerInterface::class);

            return $handler->handle($request, $response);
        }
        catch (Throwable $error) {
            try {
                return (new ErrorHandler($this->container))
                    ->handle($error, $request, $response);
            }
            catch (Throwable $fatal) {
                error_log('error handler: '. $fatal::class .': '. str_replace(["\r", "\n", "\0"], ' ', $fatal->getMessage()));

                Fallback::emit($error);
            }
        }
    }

    public function terminate(): void
    {
        $this->manager->terminate();
    }


    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->manager->register();
        $this->manager->boot();

        foreach ($this->modules as $module) {
            $module->configure();
        }

        $this->booted = true;
    }
}

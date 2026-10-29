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

use Throwable;

use function Sourcerer\config;


/**
 * Application lifecycle: services register and boot first, and only then the
 * modules configure themselves over the already configured application.
 *
 * Under FPM the kernel is created and boots once per request; long-running
 * runtimes that reuse the process across requests are not supported.
 *
 * It reads configuration through the global helpers and resolves services
 * from the global container; both live for the request lifetime under FPM.
 *
 * Error reporting is secured before configuration: the fatal fallback and the
 * production-safe ini are installed first, and development mode is enabled
 * only after the environment is known.
 */
final class Kernel
{
    private static ?self $instance = null;

    private bool $booted = false;


    /**
     * @param list<ModuleProviderInterface> $modules
     */
    private function __construct(
        private readonly ContainerInterface $container,
        private readonly ServiceManagerInterface $manager,
        private readonly array $modules
    ) { }


    /**
     * Builds the kernel once and installs the fallback and error bootstrap
     * before any configuration is read.
     */
    public static function create(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        Fallback::register();
        ErrorHandler::bootstrap();

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


    /**
     * Boots the application and dispatches the request, converting throwables
     * into error responses (or the fatal fallback if that also fails).
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $this->boot();

            /** @var RequestHandlerInterface $handler */
            $handler = $this->container->get(RequestHandlerInterface::class);

            return $handler->handle($request, $response);
        } catch (Throwable $error) {
            try {
                return (new ErrorHandler($this->container))
                    ->handle($error, $request, $response);
            }
            catch (Throwable $fatal) {
                $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $fatal->getMessage()) ?? $fatal->getMessage();

                error_log('error handler: '. $fatal::class .': '. $message);

                Fallback::emit($error);
            }
        }
    }

    /**
     * Terminates the registered service providers.
     */
    public function terminate(): void
    {
        $this->manager->terminate();
    }


    /**
     * Registers and boots providers, then configures modules; runs once.
     */
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

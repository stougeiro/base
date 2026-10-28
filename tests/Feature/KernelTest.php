<?php declare(strict_types=1);

use Sourcerer\Http\Kernel;
use Sourcerer\Log\Log;
use Sourcerer\Log\LogInterface;
use STDW\Contract\Http\RequestHandlerInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;
use Tests\Fixtures\FakeRequest;
use Tests\Fixtures\TestModule;
use Tests\Fixtures\TestProvider;

beforeEach(function () {
    bootstrap_app();
    reset_container();
    reset_kernel();

    TestProvider::$registered = 0;
    TestProvider::$booted = 0;
    TestProvider::$terminated = 0;
    TestModule::$configured = 0;

    $this->logDir = temp_dir('kernel_');
    container()->singleton(LogInterface::class, fn () => new Log($this->logDir . '/app.log'));
});

afterEach(function () {
    remove_tree($this->logDir);
});

test('kernel creates once, boots once and handles errors and requests', function () {
    $kernel = Kernel::create();

    expect(Kernel::create())->toBe($kernel);

    $request = new FakeRequest('GET', 'http://localhost/health');

    $error = $kernel->handle($request);

    expect($error->getStatus())->toBe(500)
        ->and($error->getHeader('X-Error-Id'))->not->toBeNull()
        ->and(TestProvider::$registered)->toBe(1)
        ->and(TestProvider::$booted)->toBe(1)
        ->and(TestModule::$configured)->toBe(1);

    container()->singleton(RequestHandlerInterface::class, fn () => new class implements RequestHandlerInterface {
        public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return $response->withBody('handled');
        }
    });

    $ok = $kernel->handle($request);

    expect($ok->getStatus())->toBe(200)
        ->and($ok->getBody())->toBe('handled')
        ->and(TestProvider::$registered)->toBe(1)
        ->and(TestProvider::$booted)->toBe(1)
        ->and(TestModule::$configured)->toBe(1);

    $kernel->terminate();

    expect(TestProvider::$terminated)->toBe(1);
});

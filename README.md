![PHP](https://img.shields.io/badge/PHP-%20^8.2-777BB4)
![PHPStan-Level](https://img.shields.io/badge/PHPStan-Level%209-224488)
![Pest-php](https://img.shields.io/badge/Tests-Passed-019733)
![License](https://img.shields.io/badge/License-MIT-777)

# Base

An opinionated PHP 8.2 foundation for small HTTP applications. It provides an
explicit request lifecycle, module-scoped routing and middleware, consistent
error responses, and a safe bootstrap without the weight of a full framework.

Base is intentionally small: it supplies the module, routing, error handling,
configuration, and logging primitives. Application wiring stays in the
application, where it can be inspected and replaced.

## Features

- **Per-request kernel** — providers register and boot, modules configure
  themselves, the request is handled, and providers terminate. Under FPM the
  kernel is created once per request.
- **Self-contained modules** — a module owns its routes, middleware,
  controllers, and optional views. Only its provider class is referenced from
  application configuration.
- **Secure error bootstrap** — the fatal fallback and production-safe error
  settings are installed before configuration is read. Development visibility
  is enabled only after `env('environment')` is known.
- **Routing and controllers** — routes point to controllers, route variables
  become request attributes, and module/controller middleware run around the
  controller. `OPTIONS` is answered automatically and invalid verbs receive a
  `405` with an `Allow` header.
- **Consistent error responses** — HTML or JSON bodies, `HttpException` status
  and headers, request reference IDs, `Cache-Control: no-store`, and
  `X-Content-Type-Options: nosniff`. Development responses include a source
  snippet and stack trace.
- **Safe logging** — sensitive query parameters are redacted before logging;
  the logger falls back to `error_log` when the log file is unavailable.
- **Configuration helpers** — dot-notated `env.php` and `config/*.php` files
  with explicit defaults and fast failure for missing keys. `Path` resolves
  real directories and rejects typos at registration time.
- **Small service layer** — immutable `Result`, `ActionServiceInterface`,
  `CheckServiceInterface`, and `LogInterface`.
- **Quality tooling** — PHPStan Level 9 and a Pest suite.

## Requirements

- PHP 8.2 or newer.
- Composer.
- pcov or Xdebug if you want code coverage.

The routing example below also uses `stougeiro/router`. It is not a runtime
dependency of Base; install it in the application when you use routing.

## Installation

```bash
composer require stougeiro/base
composer require stougeiro/router
```

## Quick start

This example adds a self-contained `HelloWorld` module to an application that
already has its container and routing providers wired. Base does not generate
an application skeleton; the providers used by the sample application are
described in [Application wiring](#application-wiring).

```text
my-app/
├── app/
│   ├── HelloWorld/
│   │   ├── Config/
│   │   │   ├── middlewares.php
│   │   │   └── routes.php
│   │   ├── Controller/
│   │   │   └── HelloWorldController.php
│   │   ├── Middleware/
│   │   │   ├── HelloWorldMiddleware.php
│   │   │   └── HelloWorldOnlyMiddleware.php
│   │   └── HelloWorldModuleProvider.php
│   └── Provider/
│       ├── AppServiceProvider.php
│       ├── CacheServiceProvider.php
│       ├── HttpServiceProvider.php
│       └── PipelineServiceProvider.php
├── config/
│   ├── app.php
│   └── error.php
├── env.php
├── public/
│   └── index.php
└── storage/
    └── log/
```

Everything below `app/HelloWorld` belongs to the module. The module is not
split across `app/Modules`, `app/routes`, or `app/Http/Controllers`.

### 1. Register paths and boot the kernel

```php
<?php

declare(strict_types=1);

use Sourcerer\Http\Kernel;
use Sourcerer\Path;
use STDW\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

Path::register('root', dirname(__DIR__));
Path::register('config', 'config/', 'root');
Path::register('logs', 'storage/log/', 'root');

$kernel = Kernel::create();

$kernel->handle(new Request())->send();
$kernel->terminate();
```

`Path::register()` requires the directory to exist. Duplicate keys and invalid
paths throw immediately, which catches configuration mistakes early.

`Kernel::create()` installs the fallback and error bootstrap, then reads
`app.providers` and `app.modules` from `config/app.php`.

### 2. Define the environment

```php
<?php

// env.php
return [
    'environment' => 'development', // use 'production' outside development
];
```

Only the exact value `development` enables development error handling. Any
other value, including a missing key, is treated as production.

### 3. Register the module

```php
<?php

// config/app.php
use App\HelloWorld\HelloWorldModuleProvider;

return [
    'providers' => [
        // Application wiring. See "Application wiring" below.
        // App\Provider\CacheServiceProvider::class,
        // App\Provider\HttpServiceProvider::class,
        // App\Provider\PipelineServiceProvider::class,
    ],

    'modules' => [
        HelloWorldModuleProvider::class,
    ],
];
```

The module provider is the only module class listed globally. The application
providers are covered in [Application wiring](#application-wiring).

### 4. Declare the module

```php
<?php

declare(strict_types=1);

namespace App\HelloWorld;

use Sourcerer\Module\ModuleProviderAbstracted;

final class HelloWorldModuleProvider extends ModuleProviderAbstracted
{
    public function configure(): void
    {
        $this->routes(__DIR__ . '/Config/routes.php');
        $this->middlewares(__DIR__ . '/Config/middlewares.php');
    }
}
```

`routes()` and `middlewares()` receive absolute module paths. Moving or copying
the `HelloWorld` directory therefore keeps the module intact.

If the application binds `AliasManagerInterface`, the module can also register
its own view namespace:

```php
$this->views('hello', __DIR__ . '/View');
```

View rendering is application-level; Base only exposes the registration hook.

### 5. Add the route

```php
<?php

// app/HelloWorld/Config/routes.php
use App\HelloWorld\Controller\HelloWorldController;

return [
    '/' => HelloWorldController::class,
];
```

A route file returns a map of `path => controller class`. A route miss raises
`HttpException(404)`; a route pointing to a non-controller raises
`RuntimeException`.

### 6. Add module and controller middleware

```php
<?php

// app/HelloWorld/Config/middlewares.php
use App\HelloWorld\Controller\HelloWorldController;
use App\HelloWorld\Middleware\HelloWorldMiddleware;
use App\HelloWorld\Middleware\HelloWorldOnlyMiddleware;

return [
    '*' => [
        HelloWorldMiddleware::class,
    ],

    HelloWorldController::class => [
        HelloWorldOnlyMiddleware::class,
    ],
];
```

`'*'` applies to every route in the module. A controller FQCN applies only to
that controller. Module middleware runs before controller middleware, and
repeated middleware classes are resolved once.

```php
<?php

// app/HelloWorld/Middleware/HelloWorldMiddleware.php
declare(strict_types=1);

namespace App\HelloWorld\Middleware;

use Closure;
use STDW\Contract\Http\MiddlewareInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;

final class HelloWorldMiddleware implements MiddlewareInterface
{
    public function process(
        RequestInterface $request,
        ResponseInterface $response,
        Closure $next,
    ): ResponseInterface {
        return $next($request, $response->withHeader('X-Module', 'hello'));
    }
}
```

```php
<?php

// app/HelloWorld/Middleware/HelloWorldOnlyMiddleware.php
declare(strict_types=1);

namespace App\HelloWorld\Middleware;

use Closure;
use STDW\Contract\Http\MiddlewareInterface;
use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;

final class HelloWorldOnlyMiddleware implements MiddlewareInterface
{
    public function process(
        RequestInterface $request,
        ResponseInterface $response,
        Closure $next,
    ): ResponseInterface {
        return $next($request, $response->withHeader('X-Controller', 'hello'));
    }
}
```

### 7. Write the controller

```php
<?php

// app/HelloWorld/Controller/HelloWorldController.php
declare(strict_types=1);

namespace App\HelloWorld\Controller;

use Sourcerer\Http\Controller\Controller;
use STDW\Contract\Http\ResponseInterface;

final class HelloWorldController extends Controller
{
    /** @var list<string> */
    protected array $allowed = ['get'];

    public function get(): ResponseInterface
    {
        return $this->response->withBody('Hello World');
    }
}
```

Controller handler methods are the lowercase HTTP verbs listed in `$allowed`.
`OPTIONS` is answered by the base controller with `204` and an `Allow` header.
Any other verb raises a `405` with the same header.

The response accumulated by middleware is available as `$this->response`, and
route variables are available as request attributes:

```php
$id = $this->request->getAttribute('id');
```

### 8. Run it

```bash
curl -i http://localhost:8080/
```

The response body is `Hello World`, with `X-Module: hello` and
`X-Controller: hello` set by the module middleware chain.

## How the module is loaded

`Kernel::create()` reads `app.modules` and instantiates each module provider.
On the first `handle()`, providers register and boot, then each module's
`configure()` method runs. The module loads its routes and middleware maps; the
router is resolved lazily at dispatch time, after all modules have configured.

The module is self-contained: routes, middleware, controllers, and optional
views live inside `app/HelloWorld`, and only `HelloWorldModuleProvider` appears
in `config/app.php`.

## Application wiring

Base does not ship concrete routing, cache, or view implementations. The
application supplies them through service providers. The reference application
and skeleton use these providers:

| Provider | Responsibility |
| --- | --- |
| `CacheServiceProvider` | Binds `STDW\Contract\Cache\CacheInterface` to the application's cache implementation. |
| `HttpServiceProvider` | Binds `RouteCollectionInterface` and `RouterInterface`. The router is lazy, so it snapshots routes only after modules have loaded. |
| `PipelineServiceProvider` | Binds the middleware manager, `RoutingMiddlewareCollection`, `RoutingDispatcherMiddleware`, and `RequestHandlerInterface`; adds global middleware and sets the dispatcher as the final handler. |
| `ViewServiceProvider` | Optional. Binds `ViewManagerInterface` and `AliasManagerInterface` when the application uses views. |
| `AppServiceProvider` | Application-specific services. |

If you wire the container manually, the minimum bindings used by the routing
pipeline are:

```text
STDW\Contract\Cache\CacheInterface
STDW\Contract\Http\Router\RouteCollectionInterface
STDW\Contract\Http\Router\RouterInterface
Sourcerer\Http\RoutingMiddlewareCollection
Sourcerer\Http\RoutingDispatcherMiddleware
STDW\Contract\Http\RequestHandlerInterface
```

The reference providers belong to the application or skeleton, not to
`stougeiro/base`.

## Request lifecycle

```text
Kernel::create()
├── Fallback::register()
├── ErrorHandler::bootstrap()
├── reads app.providers and app.modules
└── instantiates providers and modules

Kernel::handle()
├── ServiceManager::register()
├── ServiceManager::boot()
├── module configure() calls
├── RequestHandlerInterface dispatch
└── Throwable -> ErrorHandler
    └── ErrorHandler failure -> Fallback

Kernel::terminate()
└── ServiceManager::terminate()
```

Under FPM the kernel is created and booted once per request. Do not reuse it
across requests in a long-running runtime.

## Routing details

- Routes are arrays of `path => controller class` returned from a PHP file.
  `stougeiro/router` supports placeholders such as `/users/{id:num}`; available
  types include `any`, `num`, `word`, `slug`, `color`, `year`, `month`, `day`,
  and `token`.
- Route variables become request attributes before the controller runs.
- A route miss raises `HttpException(404)`.
- A route pointing to a class that does not implement `ControllerInterface`
  raises `RuntimeException`.
- Module middleware runs before controller middleware. Duplicate middleware
  classes are resolved only once.
- The kernel converts throwables into error responses. If the error handler
  itself fails, the fatal fallback emits a minimal HTML `500` and terminates
  the request.

## Error handling and security

`Kernel::create()` runs `Fallback::register()` and `ErrorHandler::bootstrap()`
before reading configuration. `bootstrap()` first applies a production-safe
baseline, then reads `env('environment')`; only in development does it enable
display errors and install the error handler.

`ErrorHandler` turns any throwable into a response:

- `HttpException` provides status `400`–`599` and optional headers.
- HTML or JSON is selected by `config('error.mode')` and the request's
  `Accept` header.
- Development responses include the error message, a source snippet, and a
  stack trace. Production `5xx` responses expose only a generic reason.
- `5xx` responses include an `X-Error-Id` and a reference ID in the body.
- Responses use `Cache-Control: no-store` and
  `X-Content-Type-Options: nosniff`.
- Logs include the request method, path, redacted query, client IP, and user
  agent. The logger is resolved from the container; if it is unavailable or
  invalid, logging falls back to `error_log`.

`Fallback` is the last line of defense. Its shutdown guard catches fatal errors
and emits a self-contained `500` page even when the container, configuration,
or autoloader is broken. It clears output buffers, logs the error, sends safe
headers, and exits with status `1`.

Error responses can be configured in `config/error.php`:

```php
<?php

return [
    'mode' => 'html', // 'html' or 'json'
    'messages' => [
        404 => [
            'title' => 'Not Found',
            'message' => 'The page you requested does not exist.',
        ],
    ],
    'snippet' => [
        'pad' => 10,
    ],
    'log' => [
        'redact' => ['cpf', 'cnpj'],
    ],
];
```

- `mode` selects the default format. `Accept: application/json` also selects
  JSON.
- `messages` can override the title and message for any status.
- `snippet.pad` controls how many source lines appear around an error in
  development.
- `log.redact` adds query keys to the built-in sensitive list. Matching is
  case-insensitive and substring-based, so `api_key` and `accessToken` are both
  redacted.

Security notes:

- `X-Forwarded-For` and `Client-IP` are trusted only for logging. They are
  client-controlled and must never be used for authorization decisions.
- Keep `env.php`, `config/`, and logs outside the public document root, or
  otherwise deny direct web access to them.
- `harden()` uses `ini_set()` and cannot override a server that disables it.
  Keep production `php.ini` settings correct as well.
- `Path`, `env()`, `config()`, and the kernel are per-request under FPM. Do not
  reuse a booted kernel across requests in long-running runtimes.

## Configuration helpers

```php
use Sourcerer\Path;

Path::register('root', dirname(__DIR__));
Path::register('config', 'config/', 'root');
Path::register('logs', 'storage/log/', 'root');

$root = Path::get('root');
```

`env('app.name')` reads `env.php`; `config('app.providers')` reads
`config/app.php`. Both helpers use dot notation, cache their file contents for
the lifetime of the process, and throw when a key is missing unless a default
is supplied:

```php
$name = env('app.name', 'My App');
$providers = config('app.providers', []);
```

`Path::register()` resolves the path with `realpath()` and stores it with a
trailing directory separator. Keys live for the whole process, a duplicate key
fails loudly, and there is no unregister API because FPM gives each request a
fresh process.

## Logging

Bind `LogInterface` to `Sourcerer\Log\Log` to write to
`Path::get('logs') . 'app.log'`, or pass a full file path to its constructor.
The logger writes one line per entry, appends JSON context when present, and
falls back to `error_log` if the file cannot be written.

To change buffering, rotation, or the destination, implement `LogInterface` and
bind your implementation in a service provider.

## Service layer

`Result` is an immutable outcome for application services:

```php
use Sourcerer\Service\Result;

$result = Result::success(['id' => 42]);
$result = Result::failure(null, ['User not found']);

$result->isSuccess();
$result->data();     // immutable payload or null
$result->messages(); // list<string>
```

`ActionServiceInterface::handle(array $input): ResultInterface` and
`CheckServiceInterface::check(array $input): bool` are small contracts for
action and validation services.

## Project structure

```text
src/
├── Config.php              config() helper
├── Env.php                 env() helper
├── Path.php                realpath-based path registry
├── Error/
│   ├── ErrorHandler.php    throwable-to-response handling
│   ├── ErrorRenderer.php   HTML/JSON rendering
│   ├── Fallback.php        last-resort fatal 500
│   └── Exception/
│       └── HttpException.php
├── Http/
│   ├── Kernel.php
│   ├── RoutingDispatcherMiddleware.php
│   ├── RoutingMiddlewareCollection.php
│   └── Controller/
│       ├── Controller.php
│       └── ControllerInterface.php
├── Log/
│   ├── Log.php
│   └── LogInterface.php
├── Module/
│   ├── ModuleProviderAbstracted.php
│   └── ModuleProviderInterface.php
└── Service/
    ├── ActionServiceInterface.php
    ├── CheckServiceInterface.php
    ├── Result.php
    └── ResultInterface.php
```

## Testing

```bash
vendor/bin/pest
vendor/bin/pest --coverage
vendor/bin/phpstan analyse --no-progress
composer validate --strict
```

Coverage requires pcov or Xdebug. PHPStan runs at Level 9 over `src/`.

## Contributions

Contributions are welcome. Feel free to open issues or submit pull requests.

## License

MIT. See [LICENSE](LICENSE).

<br>

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="170"/>](https://www.buymeacoffee.com/stougeiro)

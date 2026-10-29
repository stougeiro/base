![PHP](https://img.shields.io/badge/PHP-%20^8.2-777BB4)
![PHPStan-Level](https://img.shields.io/badge/PHPStan-Level%209-224488)
![Pest-php](https://img.shields.io/badge/Pest-php-019733)
![License](https://img.shields.io/badge/License-MIT-777)

# Base

An opinionated PHP 8.2 foundation for small HTTP and API applications.

Base supplies the request lifecycle, module boundaries, controllers, error
handling, configuration, and logging. It deliberately stops at the HTTP
boundary: concrete routing, cache, views, and application wiring belong to the
application.

The goal is a small, explicit core that is easy to inspect, replace, and test.

## Why Base

- **API-first by default.** Base has no view layer and no presentation
  dependency. Applications that need HTML can add their own integration.
- **Explicit over magic.** Modules are listed in configuration. Routes and
  middleware are declared in files. Only the module provider is global.
- **Safe defaults.** The fatal fallback and production-safe error settings are
  installed before configuration is read.
- **Small surface.** Composer installs the HTTP core, not a full framework.
- **Per-request state.** Under FPM, paths, configuration, and the kernel live
  for one request. There is no hidden long-running state.

## Features

- **Per-request kernel** — providers register and boot, modules configure
  themselves, the request is handled, and providers terminate.
- **Self-contained modules** — a module owns its routes, middleware, and
  controllers. Only its provider class is referenced globally.
- **Routing and controllers** — route variables become request attributes,
  middleware runs around controllers, `OPTIONS` is answered automatically, and
  invalid verbs receive `405` with an `Allow` header.
- **Consistent errors** — HTML or JSON responses, `HttpException`, request
  reference IDs, safe production responses, and configurable logging.
- **Secure bootstrap** — `development`, `staging`, and `production` modes with
  production-safe defaults.
- **Configuration helpers** — dot-notated `env.json` and `config/*.php`.
  `env()` is strict and has no default; `config()` supports defaults.
- **Path registry** — `Path` resolves real directories and fails loudly on
  invalid or duplicate registrations.
- **Small service layer** — immutable `Result` and action/check contracts.
- **Quality tooling** — PHPStan Level 9 and a Pest suite.

## Requirements

- PHP 8.2 or newer with `ext-json`.
- Composer.
- `stougeiro/router` if you use the routing example.
- pcov or Xdebug for coverage.

## Installation

```bash
composer require stougeiro/base
composer require stougeiro/router
```

## Minimal application

Base does not generate a skeleton. The application owns container and router
wiring; the reference implementations live in `stougeiro/app` and
`stougeiro/skeleton`.

The example below assumes the application has already bound its router and
request pipeline providers.

```text
my-app/
├── app/
│   └── HelloWorld/
│       ├── Config/
│       │   └── routes.php
│       ├── Controller/
│       │   └── HelloWorldController.php
│       └── HelloWorldModuleProvider.php
├── config/
│   └── app.php
├── env.json
├── public/
│   └── index.php
└── storage/
    └── log/
```

### Entry point

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
paths throw immediately, catching configuration mistakes before dispatch.

### Environment

Create `env.json` at the project root. It is data only: Base reads it with
`file_get_contents()` and `json_decode()`, never with `include`.

```json
{
  "environment": "development",
  "app": {
    "name": "My App"
  }
}
```

`environment` is required. Invalid values, malformed JSON, or a missing file
fall back to production.

- `development` exposes error details and installs the development handler.
- `staging` returns production-style responses but logs full traces.
- `production` logs traces only for `5xx`.

### Application configuration

```php
<?php

// config/app.php
use App\HelloWorld\HelloWorldModuleProvider;

return [
    'providers' => [
        // Application providers that bind the router and request pipeline.
    ],

    'modules' => [
        HelloWorldModuleProvider::class,
    ],
];
```

Providers belong to the application. Base only reads `app.providers` and
`app.modules`.

### Module

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
    }
}
```

A module can also register a middleware map with
`$this->middlewares(__DIR__ . '/Config/middlewares.php')`. Routes and
middleware files stay inside the module.

### Route and controller

```php
<?php

// app/HelloWorld/Config/routes.php
use App\HelloWorld\Controller\HelloWorldController;

return [
    '/' => HelloWorldController::class,
];
```

```php
<?php

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
`OPTIONS` is answered by the base controller. Any other verb raises a `405`
with an `Allow` header.

## Concepts and choices

### Kernel lifecycle

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

Providers register and boot first; modules configure only after services are
ready. The kernel is created and booted once per request under FPM. Reusing it
across requests in a long-running runtime is not supported.

### Modules without discovery

A module is self-contained: routes, middleware, and controllers live together.
Configuration lists only the module provider. This keeps scanning and implicit
registration out of the core, while routes and middleware remain explicit and
easy to inspect.

### Environment vs configuration

`env.json` is for environment-specific values and secrets. It is JSON so a
compromised or accidentally executable file cannot run PHP code. Base rejects
symlinks and files larger than 64 KiB, requires an object root, and limits JSON
depth.

`config/*.php` is for application configuration that benefits from PHP
expressions and defaults. `env()` is strict and throws on missing values;
`config()` accepts an explicit default.

### Error strategy

The error handler is installed before application configuration so a broken
boot still gets a safe response.

| Behavior | `development` | `staging` | `production` |
| --- | --- | --- | --- |
| Display errors | yes | no | no |
| Details in response | yes | no | no |
| Trace in log | `5xx` | all errors | `5xx` |

`HttpException` carries status `400`–`599` and validated headers. `5xx`
responses include an `X-Error-Id`; logs include the request path, redacted
query, client IP, and user agent.

`Fallback` is the last line of defense. It can emit a minimal `500` even when
the container, configuration, or autoloader is unavailable.

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

### What Base does not include

- A concrete router or route repository.
- A cache implementation.
- A view engine or view registration.
- Application service providers or a project skeleton.

Those are application decisions. The reference wiring and expansion patterns
belong in `stougeiro/app` and `stougeiro/skeleton`.

## Configuration helpers

```php
use Sourcerer\Path;

Path::register('root', dirname(__DIR__));
Path::register('config', 'config/', 'root');
Path::register('logs', 'storage/log/', 'root');

$root = Path::get('root');
```

`env('app.name')` reads `env.json`; `config('app.providers')` reads
`config/app.php`. Both use dot notation and cache file contents for the process.

```php
$name = env('app.name');
$providers = config('app.providers', []);
```

`Path`, `env()`, `config()`, and the kernel are per-request under FPM.

## Security notes

- Keep `env.json`, `config/`, and logs outside the public document root, or deny
  direct web access to them.
- `env.json` prevents code execution, but it still contains plaintext secrets.
  Use `0400`/`0440` permissions, a non-writable project root, and atomic deploys.
- Add `env.json` to `.gitignore` and `.dockerignore`; commit an
  `env.example.json` with placeholders instead.
- Validate types and allowlist any environment value used in a class name,
  path, or command.
- `harden()` uses `ini_set()` and cannot override a server that disables it.
  Keep production `php.ini` correct as well.
- `X-Forwarded-For` and `Client-IP` are for logging only, never authorization.

## Logging and services

Bind `LogInterface` to `Sourcerer\Log\Log` to write to
`Path::get('logs') . 'app.log'`, or pass a full file path. The logger appends
JSON context, truncates oversized entries, and falls back to `error_log` when
the file cannot be written.

`Result` is an immutable service outcome:

```php
use Sourcerer\Service\Result;

$result = Result::success(['id' => 42]);
$result = Result::failure(null, ['User not found']);

$result->isSuccess();
$result->data();
$result->messages();
```

`ActionServiceInterface` and `CheckServiceInterface` are small contracts for
application services.

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

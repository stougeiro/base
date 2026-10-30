![PHP](https://img.shields.io/badge/PHP-%20^8.2-777BB4)
![PHPStan-Level](https://img.shields.io/badge/PHPStan-Level%209-224488)
![Pest-php](https://img.shields.io/badge/Tests-Passed-019733)
![License](https://img.shields.io/badge/License-MIT-777)

# Base

An opinionated PHP 8.2 foundation for small HTTP and API applications.

Base supplies the request lifecycle, module boundaries, controllers, error handling, configuration, and logging. It deliberately stops at the HTTP boundary: concrete routing, cache, views, and application wiring belong to the application.

The goal is a small, explicit core that is easy to inspect, replace, and test.

## ✨ Features

- **Per-request kernel**: providers register and boot, modules configure themselves, the request is handled, and providers terminate.
- **Self-contained modules**: a module owns its routes, middleware, and controllers. Only its provider class is referenced globally.
- **Routing and controllers**: route variables become request attributes, middleware runs around controllers, `OPTIONS` is answered automatically, and invalid verbs receive `405` with an `Allow` header.
- **Consistent errors**: HTML or JSON responses, `HttpException`, request reference IDs, safe production responses, and configurable logging.
- **Secure bootstrap**: `development`, `staging`, and `production` modes with production-safe defaults.
- **Configuration helpers**: dot-notated `env.json` and `config/*.php`. `env()` is strict and has no default; `config()` supports defaults.
- **Path registry**: `Path` resolves real directories and fails loudly on invalid or duplicate registrations.
- **Small service layer**: immutable `Result` and action/check contracts.
- **Quality tooling**: PHPStan Level 9 and a Pest suite.

---

## 📦 Installation

Install via Composer:

```bash
composer require stougeiro/base
```

### Requirements

- PHP 8.2 or newer.
- `ext-json`.

### What Base Does Not Include

- A concrete router or route repository.
- A cache implementation.
- A view engine or view registration.
- Application service providers.
- A project skeleton.

Those are application decisions. Reference wiring and expansion patterns live in [stougeiro/app](https://github.com/stougeiro/app) and [stougeiro/skeleton](https://github.com/stougeiro/skeleton).

## ⚡ Performance

Base targets the FPM request lifecycle. With OPcache enabled, and route caching when routing is used, framework overhead is small compared with database and network costs.

In a reference no-op benchmark with 80 FPM workers, Base sustained **500 req/s for 60 seconds** with **p95 between 3 ms and 14 ms** and zero failed requests. This measures the Base/HTTP layer, not a complete application.

Real throughput and latency depend on database queries, external services, logging, serialization, and worker sizing. See [BENCHMARK.md](BENCHMARK.md) for methodology, environment, and caveats.

## 🚀 Usage Example

Base does not generate an application. The example below assumes the application has already bound its router and request pipeline providers.

```php
<?php

// public/index.php
declare(strict_types=1);

use Sourcerer\Http\Kernel;
use Sourcerer\Path;
use STDW\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

Path::register('root', dirname(__DIR__));
Path::register('config', 'config/', 'root');
Path::register('logs', 'storage/log/', 'root');

$kernel = Kernel::create();

try {
    $kernel->handle(new Request())->send();
} finally {
    $kernel->terminate();
}
```

`Path::register()` requires the directory to exist. Duplicate keys and invalid paths throw immediately, catching configuration mistakes before dispatch.

Create `env.json` at the project root:

```json
{
  "environment": "development",
  "app": {
    "name": "My App"
  }
}
```

`environment` is required. Invalid values, malformed JSON, or a missing file fall back to production:

- `development` exposes error details and installs the development handler.
- `staging` returns production-style responses but logs full traces.
- `production` logs traces only for `5xx`.

Register providers and modules in `config/app.php`:

```php
<?php

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

A module loads only its own surface:

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

The routing example uses `stougeiro/router`. It is an application dependency, not a Base requirement.

For complete modules, controllers, middleware, and provider wiring, use [stougeiro/app](https://github.com/stougeiro/app) or [stougeiro/skeleton](https://github.com/stougeiro/skeleton).

---

## 🧠 Why?

### API-first by default

Base has no view layer and no presentation dependency. Applications that need HTML can add their own integration without carrying view abstractions in the core.

### Explicit modules

Modules are listed in configuration. Routes and middleware are declared in files. Only the module provider is global.

This avoids scanning, implicit registration, and hidden coupling while keeping modules self-contained and easy to inspect.

### Safe error strategy

The error handler is installed before application configuration so a broken boot still gets a safe response.

| Behavior | `development` | `staging` | `production` |
| --- | --- | --- | --- |
| Display errors | yes | no | no |
| Details in response | yes | no | no |
| Trace in log | `5xx` | all errors | `5xx` |

`HttpException` carries status `400`–`599` and validated headers. `5xx` responses include an `X-Error-Id`; logs include the request path, redacted query, client IP, and user agent.

`Fallback` is the last line of defense. It can emit a minimal `500` even when the container, configuration, or autoloader is unavailable.

### Why JSON for the environment?

`env.json` is hierarchical, which keeps related values grouped and readable. Flat files such as `.env` can represent hierarchy only through key-name conventions, which become harder to understand as the file grows.

JSON is also data-only. Base reads it with `file_get_contents()` and `json_decode()`, never with `include`, so the environment file cannot execute PHP code. It is practically a standard format, natively supported by PHP, editors, and tooling.

YAML, NEON, TOML, and similar formats would require an additional parser or translator, add a dependency, introduce implicit rules, and increase the learning curve without a proportional gain for a small environment file.

### Strict environment, configurable application

`env()` is strict and throws on missing values because an absent environment value is usually a deployment error. `config()` accepts explicit defaults because application configuration often has a safe fallback.

Both fail fast instead of silently returning partial data.

### FPM runtime model

Base targets PHP-FPM. Although workers are reused, PHP resets userland state for every request: `static` caches, the kernel singleton, the container, `ini_set()` changes, and error handlers do not carry over.

Request input and response emission are SAPI-based through `stougeiro/http`. The application must call `terminate()` after sending the response, ideally in a `finally` block.

Long-running runtimes that reuse the process across requests are not supported.

---

## 🤝 Contributions

Contributions are welcome. Feel free to open issues or submit pull requests.

<br>

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="170"/>](https://www.buymeacoffee.com/stougeiro)

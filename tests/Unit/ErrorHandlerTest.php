<?php declare(strict_types=1);

use Sourcerer\Error\ErrorHandler;
use Sourcerer\Error\Exception\HttpException;
use Sourcerer\Log\Log;
use Sourcerer\Log\LogInterface;
use STDW\Container\Container;
use STDW\Http\Response;
use Tests\Fixtures\FakeRequest;

beforeEach(function () {
    bootstrap_app();

    $this->logDir = temp_dir('log_');
    $this->logFile = $this->logDir . '/app.log';
    $this->container = new Container();
    $this->container->singleton(LogInterface::class, fn () => new Log($this->logFile));
    $this->handler = new ErrorHandler($this->container);
});

afterEach(function () {
    remove_tree($this->logDir);
});

test('error handler returns 500 for generic throwable', function () {
    $request = new FakeRequest('GET', 'http://localhost/boom?x=1', ['Accept' => 'text/html']);
    $response = $this->handler->handle(new RuntimeException("Boom\nline"), $request, new Response());

    expect($response->getStatus())->toBe(500)
        ->and($response->getHeader('X-Error-Id'))->toMatch('/^[0-9A-F]{16}$/')
        ->and($response->getHeader('Content-Type'))->toContain('text/html')
        ->and($response->getHeader('Cache-Control'))->toBe('no-store')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getBody())->toContain('Internal Server Error')
        ->and($response->getBody())->not->toContain('Boom');

    $log = file_get_contents($this->logFile);

    expect($log)->toContain('ERROR')
        ->and($log)->toContain('Boom line')
        ->and($log)->toContain('"status":500')
        ->and($log)->toContain('"trace":');
});

test('error handler returns HttpException status and copies its headers', function () {
    $response = $this->handler->handle(
        new HttpException(405, 'Method Not Allowed', ['Allow' => 'GET, OPTIONS']),
        new FakeRequest('POST', 'http://localhost/users/1', ['Accept' => 'text/html']),
        new Response(),
    );

    expect($response->getStatus())->toBe(405)
        ->and($response->getHeader('Allow'))->toBe('GET, OPTIONS')
        ->and($response->getHeader('X-Error-Id'))->toBeNull()
        ->and($response->getBody())->toContain('Method Not Allowed');
});

test('error handler answers json when Accept asks for it', function () {
    $request = new FakeRequest('GET', 'http://localhost/boom', ['Accept' => 'application/json']);
    $response = $this->handler->handle(new RuntimeException('Boom'), $request, new Response());

    $decoded = json_decode((string) $response->getBody(), true);

    expect($response->getHeader('Content-Type'))->toContain('application/json')
        ->and($decoded['status'])->toBe('error')
        ->and($decoded['code'])->toBe(500)
        ->and($decoded['id'])->toBe($response->getHeader('X-Error-Id'))
        ->and($decoded)->not->toHaveKey('details');
});

test('error handler logs the forwarded client ip', function () {
    $request = new FakeRequest('GET', 'http://localhost/', [
        'Accept' => 'text/html',
        'X-Forwarded-For' => '203.0.113.7, 10.0.0.1',
    ]);

    $this->handler->handle(new RuntimeException('Boom'), $request, new Response());

    expect(file_get_contents($this->logFile))
        ->toContain('"ip":"203.0.113.7"');
});

test('error handler bootstrap secures production ini', function () {
    $display = ini_get('display_errors');
    $log = ini_get('log_errors');
    $ignore = ini_get('zend.exception_ignore_args');
    $level = error_reporting();

    try {
        ErrorHandler::bootstrap();

        expect(ini_get('display_errors'))->toBe('0')
            ->and(ini_get('log_errors'))->toBe('1')
            ->and(ini_get('zend.exception_ignore_args'))->toBe('1')
            ->and(error_reporting())->toBe(E_ALL);

        $error = (static function (string $secret): RuntimeException {
            return new RuntimeException('Boom');
        })('trace-secret');

        $this->handler->handle(
            $error,
            new FakeRequest('GET', 'http://localhost/', ['Accept' => 'text/html']),
            new Response(),
        );

        expect((string) file_get_contents($this->logFile))->not->toContain('trace-secret');
    } finally {
        if ($display !== false) {
            ini_set('display_errors', $display);
        }

        if ($log !== false) {
            ini_set('log_errors', $log);
        }

        if ($ignore !== false) {
            ini_set('zend.exception_ignore_args', $ignore);
        }

        error_reporting($level);
    }
});

test('error handler bootstrap enables development ini and handler', function () {
    $root = temp_dir('eh_boot_');
    file_put_contents($root . '/env.php', '<?php return ["environment" => "development"];');

    $export = var_export($root, true);
    $code = '\Sourcerer\Path::register("root", ' . $export . ');'
        . '\Sourcerer\Error\ErrorHandler::bootstrap();'
        . 'echo ini_get("display_errors") . "|" . ini_get("zend.exception_ignore_args") . "|" . ini_get("log_errors") . "|" . error_reporting() . "|";'
        . '@trigger_error("deprecated", E_USER_DEPRECATED);'
        . '@trigger_error("notice", E_USER_NOTICE);'
        . 'try { trigger_error("boom", E_USER_WARNING); echo "no-throw"; } catch (\ErrorException $e) { echo "caught"; }';

    try {
        expect(trim(run_php($code)))->toBe('1|0|1|' . E_ALL . '|caught');
    } finally {
        remove_tree($root);
    }
});

test('error handler redacts sensitive query keys by substring', function () {
    $request = new FakeRequest(
        'GET',
        'http://localhost/boom?api_key=secret&access_token=abc&page=2&q=hello',
        ['Accept' => 'text/html'],
    );

    $this->handler->handle(new RuntimeException('Boom'), $request, new Response());

    $log = (string) file_get_contents($this->logFile);

    expect($log)->toContain('api_key=%5Bredacted%5D')
        ->and($log)->toContain('access_token=%5Bredacted%5D')
        ->and($log)->toContain('page=2')
        ->and($log)->toContain('q=hello');
});

test('error handler falls back to the remote address for the log', function () {
    $previous = $_SERVER['REMOTE_ADDR'] ?? null;
    $_SERVER['REMOTE_ADDR'] = '192.0.2.10';

    try {
        $this->handler->handle(
            new RuntimeException('Boom'),
            new FakeRequest('GET', 'http://localhost/', ['Accept' => 'text/html']),
            new Response(),
        );

        expect((string) file_get_contents($this->logFile))->toContain('"ip":"192.0.2.10"');
    } finally {
        if ($previous === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $previous;
        }
    }
});

test('error handler ignores a non-array override for the status', function () {
    $response = $this->handler->handle(
        new HttpException(418, 'Teapot'),
        new FakeRequest('GET', 'http://localhost/', ['Accept' => 'text/html']),
        new Response(),
    );

    expect($response->getStatus())->toBe(418)
        ->and($response->getBody())->toContain('Teapot');
});

test('error handler ignores invalid header names from HttpException', function () {
    $response = $this->handler->handle(
        new HttpException(400, 'Bad Request', ['Bad Header' => 'value', 'X-Valid' => 'ok']),
        new FakeRequest('GET', 'http://localhost/', ['Accept' => 'text/html']),
        new Response(),
    );

    expect($response->hasHeader('Bad Header'))->toBeFalse()
        ->and($response->getHeader('X-Valid'))->toBe('ok');
});

test('error handler logs Client-IP when X-Forwarded-For is absent', function () {
    $request = new FakeRequest('GET', 'http://localhost/', [
        'Accept' => 'text/html',
        'Client-IP' => '198.51.100.9',
    ]);

    $this->handler->handle(new RuntimeException('Boom'), $request, new Response());

    expect(file_get_contents($this->logFile))->toContain('"ip":"198.51.100.9"');
});

test('error handler falls back to error_log when the log service is invalid', function () {
    $previous = ini_set('error_log', $this->logDir . '/php.log');
    $container = new Container();
    $container->singleton(LogInterface::class, fn () => new stdClass());

    try {
        $response = (new ErrorHandler($container))->handle(
            new RuntimeException('Boom'),
            new FakeRequest('GET', 'http://localhost/', ['Accept' => 'text/html']),
            new Response(),
        );

        expect($response->getStatus())->toBe(500);
    } finally {
        ini_set('error_log', $previous === false ? '' : $previous);
    }
});

test('error handler reveals details in development and json config mode', function () {
    $root = temp_dir('eh_');
    mkdir($root . '/config');
    mkdir($root . '/logs');
    file_put_contents($root . '/env.php', '<?php return ["environment" => "development"];');
    file_put_contents($root . '/config/error.php', '<?php return ["mode" => "json", "messages" => []];');

    $export = var_export($root, true);
    $code = '\Sourcerer\Path::register("root", ' . $export . ');'
        . '\Sourcerer\Path::register("config", ' . $export . ' . "/config");'
        . '\Sourcerer\Path::register("logs", ' . $export . ' . "/logs");'
        . '$c = new \STDW\Container\Container();'
        . '$c->singleton(\Sourcerer\Log\LogInterface::class, fn () => new \Sourcerer\Log\Log(' . $export . ' . "/logs/app.log"));'
        . '$h = new \Sourcerer\Error\ErrorHandler($c);'
        . '$r = $h->handle(new \RuntimeException("Secret detail"), new \Tests\Fixtures\FakeRequest("GET", "http://localhost/"), new \STDW\Http\Response());'
        . 'echo $r->getStatus() . "|" . $r->getHeader("Content-Type") . "|" . $r->getBody();';

    try {
        [$status, $type, $body] = explode('|', trim(run_php($code)), 3);
        $decoded = json_decode($body, true);

        expect((int) $status)->toBe(500)
            ->and($type)->toContain('application/json')
            ->and($decoded['message'])->toBe('Secret detail')
            ->and($decoded['details']['type'])->toBe(RuntimeException::class);
    } finally {
        remove_tree($root);
    }
});

<?php

use Sourcerer\Path;
use Sourcerer\Http\Kernel;
use STDW\Contract\Container\ContainerInterface;

pest()->extend(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Runtime fixtures
|--------------------------------------------------------------------------
*/

function fixture_root(): string
{
    $root = sys_get_temp_dir() . '/sourcerer_fixtures';
    $config = $root . '/config';
    $logs = $root . '/logs';

    foreach ([$root, $config, $logs] as $dir) {
        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    file_put_contents($root . '/env.php', '<?php return ["environment" => "production", "app" => ["name" => "Sourcerer"]];');
    file_put_contents($config . '/error.php', '<?php return ["mode" => "html", "messages" => [418 => "teapot"], "snippet" => ["pad" => "12"]];');
    file_put_contents($config . '/app.php', '<?php return ["providers" => [\Tests\Fixtures\TestProvider::class], "modules" => [\Tests\Fixtures\TestModule::class]];');

    return $root;
}

function bootstrap_app(): void
{
    reset_path();

    $root = fixture_root();

    Path::register('root', $root);
    Path::register('config', $root . '/config');
    Path::register('logs', $root . '/logs');
}

/*
|--------------------------------------------------------------------------
| Static state resets
|--------------------------------------------------------------------------
*/

function reset_path(): void
{
    $property = (new ReflectionClass(Path::class))->getProperty('paths');
    $property->setAccessible(true);
    $property->setValue([]);
}

function reset_container(): void
{
    $container = container();

    foreach (['entries', 'resolved'] as $name) {
        $property = (new ReflectionClass($container))->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($container, []);
    }

    $container->singleton(ContainerInterface::class, fn () => $container);
}

function reset_kernel(): void
{
    $property = (new ReflectionClass(Kernel::class))->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null);
}

/*
|--------------------------------------------------------------------------
| Temporary files
|--------------------------------------------------------------------------
*/

function temp_dir(string $prefix = 'sourcerer_'): string
{
    $dir = sys_get_temp_dir() . '/' . $prefix . uniqid();
    mkdir($dir);

    return $dir;
}

function temp_php(string $code): string
{
    $file = sys_get_temp_dir() . '/sourcerer_' . uniqid() . '.php';
    file_put_contents($file, $code);

    return $file;
}

function remove_file(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

function remove_dir(string $dir): void
{
    if (is_dir($dir)) {
        rmdir($dir);
    }
}

function remove_tree(string $dir): void
{
    if ( ! is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir . '/' . $entry;

        if (is_dir($path)) {
            remove_tree($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

/*
|--------------------------------------------------------------------------
| Subprocess (cold-start isolation)
|--------------------------------------------------------------------------
*/

function run_php(string $code): string
{
    $autoload = realpath(__DIR__ . '/../vendor/autoload.php');
    $script = 'require ' . var_export($autoload, true) . ';' . $code;

    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1';

    return (string) shell_exec($command);
}

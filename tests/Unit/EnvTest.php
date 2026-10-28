<?php declare(strict_types=1);

use Sourcerer\Path;
use function Sourcerer\env;

beforeEach(fn () => reset_path());

function env_run(string $dir, string $expression): string
{
    $register = '\Sourcerer\Path::register("root", ' . var_export($dir, true) . ');';

    return trim(run_php($register . $expression));
}

test('env reads dot-notated item from env.php', function () {
    $dir = temp_dir('env_');
    file_put_contents($dir . '/env.php', '<?php return ["environment" => "testing", "db" => ["host" => "localhost"]];');

    try {
        expect(env_run($dir, 'echo \Sourcerer\env("environment") . "|" . \Sourcerer\env("db.host");'))
            ->toBe('testing|localhost');
    } finally {
        remove_file($dir . '/env.php');
        remove_dir($dir);
    }
});

test('env returns default when key missing', function () {
    $dir = temp_dir('env_');
    file_put_contents($dir . '/env.php', '<?php return [];');

    try {
        expect(env_run($dir, 'echo \Sourcerer\env("missing.key", "default_env");'))
            ->toBe('default_env');
    } finally {
        remove_file($dir . '/env.php');
        remove_dir($dir);
    }
});

test('env throws when no default and key missing', function () {
    $dir = temp_dir('env_');
    file_put_contents($dir . '/env.php', '<?php return [];');

    try {
        expect(env_run($dir, 'try { \Sourcerer\env("missing.key"); echo "no-throw"; } catch (\Throwable $e) { echo $e::class; }'))
            ->toBe(RuntimeException::class);
    } finally {
        remove_file($dir . '/env.php');
        remove_dir($dir);
    }
});

test('env returns default when file missing', function () {
    $dir = temp_dir('env_');

    try {
        expect(env_run($dir, 'echo \Sourcerer\env("any.key", "fallback");'))
            ->toBe('fallback');
    } finally {
        remove_dir($dir);
    }
});

test('env throws when no default and file missing', function () {
    $dir = temp_dir('env_');

    try {
        expect(env_run($dir, 'try { \Sourcerer\env("any.key"); echo "no-throw"; } catch (\Throwable $e) { echo $e::class; }'))
            ->toBe(RuntimeException::class);
    } finally {
        remove_dir($dir);
    }
});

test('env throws when file does not return array', function () {
    $dir = temp_dir('env_');
    file_put_contents($dir . '/env.php', '<?php return 42;');

    try {
        expect(env_run($dir, 'try { \Sourcerer\env("test"); echo "no-throw"; } catch (\Throwable $e) { echo $e::class; }'))
            ->toBe(RuntimeException::class);
    } finally {
        remove_file($dir . '/env.php');
        remove_dir($dir);
    }
});

test('env reads the canonical fixture in-process', function () {
    bootstrap_app();

    expect(env('environment'))->toBe('production')
        ->and(env('app.name'))->toBe('Sourcerer')
        ->and(env('app'))->toBe(['name' => 'Sourcerer']);
});

test('env returns default for a missing key in-process', function () {
    bootstrap_app();

    expect(env('missing.key', 'fallback'))->toBe('fallback');
});

test('env throws for a missing key without default in-process', function () {
    bootstrap_app();

    expect(fn () => env('missing.key'))->toThrow(RuntimeException::class);
});

test('env returns default when root path not registered', function () {
    expect(trim(run_php('echo \Sourcerer\env("any.key", "fallback");')))->toBe('fallback');
});

test('env throws when root path not registered and no default', function () {
    $output = run_php('try { \Sourcerer\env("any.key"); echo "no-throw"; } catch (\Throwable $e) { echo $e::class; }');

    expect(trim($output))->toBe(RuntimeException::class);
});

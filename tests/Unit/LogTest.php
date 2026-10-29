<?php declare(strict_types=1);

use Sourcerer\Log\Log;
use Sourcerer\Path;

beforeEach(fn () => reset_path());

test('log writes error message to file', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->error('Test error message', ['user_id' => 123]);

        expect(file_exists($tempFile))->toBeTrue();
        $content = file_get_contents($tempFile);
        expect($content)->toContain('ERROR')
            ->and($content)->toContain('Test error message')
            ->and($content)->toContain('user_id');
    } finally {
        remove_file($tempFile);
    }
});

test('log writes warn message', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->warn('Warning message');

        $content = file_get_contents($tempFile);
        expect($content)->toContain('WARN')
            ->and($content)->toContain('Warning message');
    } finally {
        remove_file($tempFile);
    }
});

test('log writes info message', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->info('Info message');

        $content = file_get_contents($tempFile);
        expect($content)->toContain('INFO')
            ->and($content)->toContain('Info message');
    } finally {
        remove_file($tempFile);
    }
});

test('log writes debug message', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->debug('Debug message');

        $content = file_get_contents($tempFile);
        expect($content)->toContain('DEBUG')
            ->and($content)->toContain('Debug message');
    } finally {
        remove_file($tempFile);
    }
});

test('log write without context', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->error('Simple error');

        $content = file_get_contents($tempFile);
        expect($content)->toContain('Simple error');
    } finally {
        remove_file($tempFile);
    }
});

test('log write with unknown level defaults to DEBUG', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->write('Unknown level message', 99);

        $content = file_get_contents($tempFile);
        expect($content)->toContain('DEBUG')
            ->and($content)->toContain('Unknown level message');
    } finally {
        remove_file($tempFile);
    }
});

test('log cleans newlines from message', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->error("multi\nline\rmessage\0");

        $content = file_get_contents($tempFile);
        expect($content)->toContain('multi line message ');
    } finally {
        remove_file($tempFile);
    }
});

test('log truncates oversized messages and context', function () {
    $tempFile = sys_get_temp_dir() . '/app_' . uniqid() . '.log';

    try {
        $log = new Log($tempFile);
        $log->error(str_repeat('m', 5000), ['blob' => str_repeat('c', 9000)]);

        $content = (string) file_get_contents($tempFile);

        expect($content)->toContain('...')
            ->and($content)->toContain('"_truncated":true')
            ->and($content)->not->toContain(str_repeat('c', 9000));
    } finally {
        remove_file($tempFile);
    }
});

test('log falls back to error_log when the file cannot be written', function () {
    $dir = temp_dir('log_');
    $phpLog = $dir . '/php.log';
    $previousLog = ini_set('error_log', $phpLog);

    set_error_handler(fn () => true);

    try {
        (new Log($dir))->info('unwritable target');
    } finally {
        restore_error_handler();
        ini_set('error_log', $previousLog === false ? '' : $previousLog);
    }

    try {
        expect(file_get_contents($phpLog))->toContain('unwritable target');
    } finally {
        remove_tree($dir);
    }
});

test('log defaults to logs path when no file is given', function () {
    bootstrap_app();
    $logFile = Path::get('logs') . 'app.log';

    try {
        (new Log())->info('default path message');

        expect(file_get_contents($logFile))->toContain('default path message');
    } finally {
        remove_file($logFile);
    }
});

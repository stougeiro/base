<?php declare(strict_types=1);

use Sourcerer\Path;

beforeEach(fn () => reset_path());

test('path registration and resolution works', function () {
    $dir = temp_dir('path_');

    try {
        Path::register('test_root', $dir);
        $resolved = Path::get('test_root');

        expect(str_starts_with($resolved, $dir))->toBeTrue();
    } finally {
        remove_dir($dir);
    }
});

test('path registration with $from parameter resolves relative path', function () {
    $parent = temp_dir('path_parent_');
    $child = $parent . '/sub';
    mkdir($child);

    try {
        Path::register('parent', $parent);
        Path::register('child', '/sub', 'parent');

        $resolved = Path::get('child');
        expect(str_starts_with($resolved, $child))->toBeTrue();
    } finally {
        remove_dir($child);
        remove_dir($parent);
    }
});

test('path resolution throws when key not registered', function () {
    expect(fn () => Path::get('non_existent_key'))->toThrow(RuntimeException::class);
});

test('path registration with invalid path throws', function () {
    expect(fn () => Path::register('bad', '/non/existent/path'))
        ->toThrow(RuntimeException::class);
});

test('path registration with unknown $from key throws', function () {
    expect(fn () => Path::register('bad', '/sub', 'unknown_key'))
        ->toThrow(RuntimeException::class);
});

test('path registration twice throws', function () {
    $dir = temp_dir('path_');

    try {
        Path::register('duplicated', $dir);

        expect(fn () => Path::register('duplicated', $dir))->toThrow(RuntimeException::class);
    } finally {
        remove_dir($dir);
    }
});

test('path returned always ends with directory separator', function () {
    $dir = temp_dir('path_');

    try {
        Path::register('root', $dir);
        $resolved = Path::get('root');

        expect($resolved)->toEndWith('/');
    } finally {
        remove_dir($dir);
    }
});

<?php declare(strict_types=1);

use Sourcerer\Error\Fallback;
use Sourcerer\Error\ErrorRenderer;

test('fallback id generates 16-character hex string', function () {
    expect(Fallback::id())->toMatch('/^[0-9A-F]{16}$/');
});

test('fallback id is unique per call', function () {
    expect(Fallback::id())->not->toBe(Fallback::id());
});

test('fallback document contains id and status', function () {
    $doc = Fallback::document('TEST1234ID');

    expect($doc)->toContain('500')
        ->and($doc)->toContain('Internal Server Error')
        ->and($doc)->toContain('TEST1234ID')
        ->and($doc)->toContain('<!DOCTYPE html>');
});

test('fallback document escapes html special chars in id', function () {
    $doc = Fallback::document('<script>');

    expect($doc)->toContain('&lt;script&gt;')
        ->and($doc)->not->toContain('<script>');
});

test('error renderer clamps snippet pad to range 5-30', function () {
    $property = (new ReflectionClass(ErrorRenderer::class))->getProperty('snippetPad');
    $property->setAccessible(true);

    expect($property->getValue(new ErrorRenderer(0)))->toBe(5)
        ->and($property->getValue(new ErrorRenderer(100)))->toBe(30)
        ->and($property->getValue(new ErrorRenderer(10)))->toBe(10);
});

test('error renderer html in production mode hides trace and snippet', function () {
    $html = (new ErrorRenderer())->render(new RuntimeException('Secret error'), 'ERROR001', 500, false, false);

    expect($html)->toContain('500')
        ->and($html)->toContain('Internal Server Error')
        ->and($html)->toContain('ERROR001')
        ->and($html)->not->toContain('Secret error')
        ->and($html)->not->toContain('<pre>')
        ->and($html)->not->toContain('<table');
});

test('error renderer html in development mode shows trace and snippet', function () {
    $html = (new ErrorRenderer())->render(new RuntimeException('Dev error'), 'ERROR002', 500, true, false);

    expect($html)->toContain('500')
        ->and($html)->toContain('Dev error')
        ->and($html)->toContain('<pre>')
        ->and($html)->toContain('<table')
        ->and($html)->toContain('class="hit"');
});

test('error renderer snippet is empty when the file is unreadable', function () {
    $file = temp_php('<?php throw new RuntimeException("boom");');
    $error = null;

    try {
        include $file;
    } catch (Throwable $e) {
        $error = $e;
    } finally {
        remove_file($file);
    }

    $html = (new ErrorRenderer())->render($error, 'id', 500, true, false);

    expect($html)->not->toContain('<table');
});

test('error renderer snippet is empty when the source cannot be opened', function () {
    $file = temp_php('<?php throw new RuntimeException("boom");');
    $error = null;

    try {
        include $file;
    } catch (Throwable $e) {
        $error = $e;
    }

    unlink($file);
    mkdir($file);

    try {
        $html = (new ErrorRenderer())->render($error, 'id', 500, true, false);

        expect($html)->not->toContain('<table');
    } finally {
        rmdir($file);
    }
});

test('error renderer json in production mode hides details', function () {
    $json = (new ErrorRenderer())->render(new RuntimeException('Hidden error'), 'ERROR003', 500, false, true);
    $decoded = json_decode($json, true);

    expect($decoded['status'])->toBe('error')
        ->and($decoded['code'])->toBe(500)
        ->and($decoded['title'])->toBe('Internal Server Error')
        ->and($decoded['id'])->toBe('ERROR003')
        ->and($decoded)->not->toHaveKey('details');
});

test('error renderer json in development mode shows details', function () {
    $json = (new ErrorRenderer())->render(new RuntimeException('Dev json error'), 'ERROR004', 500, true, true);
    $decoded = json_decode($json, true);

    expect($decoded['details']['type'])->toBe(RuntimeException::class)
        ->and($decoded['details'])->toHaveKey('trace')
        ->and($decoded['message'])->toBe('Dev json error');
});

test('error renderer shows message for client errors in production', function () {
    $html = (new ErrorRenderer())->render(new RuntimeException('Validation failed'), 'ERROR005', 400, false, false);

    expect($html)->toContain('Validation failed');
});

test('error renderer uses override title and message', function () {
    $override = ['title' => 'Custom Title', 'message' => 'Custom Message'];
    $html = (new ErrorRenderer())->render(new RuntimeException('Original message'), 'ERR006', 500, false, false, $override);

    expect($html)->toContain('Custom Title')
        ->and($html)->toContain('Custom Message')
        ->and($html)->not->toContain('Original message');
});

test('error renderer json with override title', function () {
    $json = (new ErrorRenderer())->render(new RuntimeException('Original'), 'ERR007', 500, false, true, ['title' => 'JSON Title']);
    $decoded = json_decode($json, true);

    expect($decoded['title'])->toBe('JSON Title');
});

test('error renderer reason for known status codes', function () {
    $renderer = new ErrorRenderer();
    $error = new RuntimeException('');

    expect($renderer->render($error, 'id', 400, false, false))->toContain('Bad Request')
        ->and($renderer->render($error, 'id', 404, false, false))->toContain('Not Found')
        ->and($renderer->render($error, 'id', 500, false, false))->toContain('Internal Server Error');
});

test('error renderer reason for unknown status falls back to Error', function () {
    $html = (new ErrorRenderer())->render(new RuntimeException(''), 'id', 999, false, false);

    expect($html)->toContain('999')
        ->and($html)->toContain('Error');
});

test('error renderer with message equal to title omits detail paragraph', function () {
    $html = (new ErrorRenderer())->render(new RuntimeException('Bad Request'), 'id', 400, false, false);

    expect($html)->toContain('<h2>Bad Request</h2>')
        ->and($html)->not->toContain('<p>Bad Request</p>');
});

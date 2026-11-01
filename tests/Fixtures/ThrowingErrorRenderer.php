<?php declare(strict_types=1);

namespace Tests\Fixtures;

use RuntimeException;
use Sourcerer\Contract\Error\ErrorRendererInterface;
use Throwable;


final class ThrowingErrorRenderer implements ErrorRendererInterface
{
    /**
     * @param array<string, mixed> $override
     */
    public function render(
        Throwable $error,
        string $id,
        int $status,
        bool $development,
        bool $json,
        array $override = [],
    ): string {
        throw new RuntimeException('renderer failed');
    }
}

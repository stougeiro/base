<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Sourcerer\Contract\Error\ErrorRendererInterface;
use Throwable;


final class CustomErrorRenderer implements ErrorRendererInterface
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
        return 'custom:'. $status .':'. $id .':'. ($json ? 'json' : 'html');
    }
}

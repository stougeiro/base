<?php declare(strict_types=1);

namespace Sourcerer\Error;

use Throwable;


/**
 * Renders the response body for a throwable already classified by the
 * ErrorHandler. Implementations may use the default HTML/JSON renderer or an
 * application-specific presentation layer.
 */
interface ErrorRendererInterface
{
    /**
     * @param array<string, mixed> $override The configured entry for the status.
     */
    public function render(
        Throwable $error,
        string $id,
        int $status,
        bool $development,
        bool $json,
        array $override = [],
    ): string;
}

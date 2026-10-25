<?php declare(strict_types=1);

namespace Sourcerer\Error\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * An error that already knows the HTTP answer: the status to send and the
 * headers that must travel with it (e.g. Allow on a 405).
 */
final class HttpException extends \RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        string $message = '',
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException("HttpException status must be between 400 and 599, got {$status}");
        }

        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
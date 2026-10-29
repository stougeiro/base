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
    /** @var array<string, string> */
    private readonly array $headers;


    /**
     * @param array<array-key, mixed> $headers
     */
    public function __construct(
        private readonly int $status,
        string $message = '',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException("HttpException status must be between 400 and 599, got {$status}");
        }

        foreach ($headers as $name => $value) {
            if (
                   ! is_string($name)
                || ! is_string($value)
                || strlen($value) > 8192
                || preg_match('/\A[A-Za-z0-9-]{1,100}\z/', $name) !== 1
                || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            ) {
                throw new InvalidArgumentException('HttpException headers must use valid token names and bounded, single-line string values');
            }
        }

        /** @var array<string, string> $headers */
        $this->headers = $headers;

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
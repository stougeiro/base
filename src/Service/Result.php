<?php declare(strict_types=1);

namespace Sourcerer\Service;

use STDW\Immutable\Contract\ImmutableInterface;


/**
 * Immutable service outcome: the payload, whether the operation succeeded and
 * the messages that explain a failure.
 */
final readonly class Result implements ResultInterface
{
    private ?ImmutableInterface $result;


    /**
     * @param ?array<mixed> $data
     * @param array<int, string> $messages
     */
    private function __construct(
        ?array $data,
        private bool $success,
        private array $messages = [],
    ) {
        $this->result = ($data === null) ? null : immutable($data);
    }


    /**
     * @param ?array<mixed> $data
     * @param array<int, string> $messages
     * @return self
     */
    public static function success(?array $data = null, array $messages = []): self
    {
        return new self($data, true, $messages);
    }

    /**
     * @param ?array<mixed> $data
     * @param array<int, string> $messages
     * @return self
     */
    public static function failure(?array $data = null, array $messages = []): self
    {
        return new self($data, false, $messages);
    }


    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function data(): ?ImmutableInterface
    {
        return $this->result;
    }

    /**
     * @return array<int, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}

<?php declare(strict_types=1);

namespace Sourcerer\Service;

use STDW\Immutable\Contract\ImmutableInterface;


/**
 * The outcome of a service operation. It is not the database Result: the
 * database one answers rows/validity and is not manipulated by the developer;
 * this one carries what an action or a check returns and why it failed.
 */
interface ResultInterface
{
    public function isSuccess(): bool;

    public function data(): ImmutableInterface;

    /**
     * @return array<int, string>
     */
    public function messages(): array;
}

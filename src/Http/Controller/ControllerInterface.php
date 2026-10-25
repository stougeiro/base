<?php declare(strict_types=1);

namespace Sourcerer\Http\Controller;

use STDW\Contract\Http\MiddlewareInterface;
use STDW\Contract\Http\ResponseInterface;


/**
 * A controller declares the HTTP verbs it answers in $allowed ('get', 'post',
 * 'purge', ...) — the framework always answers 'options'. Nothing is
 * discovered, so helpers keep any name and visibility.
 */
interface ControllerInterface extends MiddlewareInterface
{
    public function options(): ResponseInterface;
}

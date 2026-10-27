<?php declare(strict_types=1);

namespace Sourcerer\Http\Controller;

use STDW\Contract\Http\RequestInterface;
use STDW\Contract\Http\ResponseInterface;
use STDW\Http\Response\EmptyResponse;

use Sourcerer\Error\Exception\HttpException;

use Closure;


/**
 * Base controller: the verbs are the lowercase names declared in the concrete
 * controller's $allowed list; 'options' is always answered by the framework.
 * Nothing is discovered, so helpers keep any name and visibility. The
 * constructor is left free for the concrete controller to use with
 * ServiceFactoryInterface and container::make().
 */
abstract class Controller implements ControllerInterface
{
    /**
     * Verbs the concrete controller answers, without 'options'.
     *
     * @var list<string>
     */
    protected array $allowed = [];

    protected RequestInterface $request;

    protected ResponseInterface $response;


    /**
     * @return ResponseInterface
     */
    final public function options(): ResponseInterface
    {
        $response = new EmptyResponse();

        return $response
            ->from($this->response)
            ->withHeader('Allow', $this->allowHeader());
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response The response accumulated by the middlewares.
     * @param Closure $next
     * @return ResponseInterface
     */
    final public function process(RequestInterface $request, ResponseInterface $response, Closure $next): ResponseInterface
    {
        $this->request = $request;
        $this->response = $response;

        $method = strtolower($this->request->getMethod());

        if ($method !== 'options' && ! in_array($method, $this->allowed, true)) {
            return $this->notAllowed();
        }

        /** @var callable(): ResponseInterface $handler */
        $handler = [$this, $method];

        return $handler();
    }


    /**
     * @return ResponseInterface
     */
    final protected function notAllowed(): ResponseInterface
    {
        throw new HttpException(405, 'Method Not Allowed', ['Allow' => $this->allowHeader()]);
    }

    /**
     * @return string
     */
    final protected function allowHeader(): string
    {
        return implode(', ', array_map('strtoupper', [...$this->allowed, 'options']));
    }
}

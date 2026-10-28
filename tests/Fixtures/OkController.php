<?php declare(strict_types=1);

namespace Tests\Fixtures;

use STDW\Contract\Http\ResponseInterface;

use Sourcerer\Http\Controller\Controller;


class OkController extends Controller
{
    /** @var list<string> */
    protected array $allowed = ['get'];


    public function get(): ResponseInterface
    {
        return $this->response->withBody('user:' . $this->request->getAttribute('id'));
    }
}

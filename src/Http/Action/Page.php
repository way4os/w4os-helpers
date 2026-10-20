<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Action;

use OpenSim\Helpers\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** A page of the site: `/` is the home page, `/welcome` the splash page. */
final class Page
{
    public function __construct(private View $view) {}

    /** @param array<string,string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $page = $request->getUri()->getPath() === '/welcome' ? 'splash' : 'home';
        $html = $this->view->page($page, $request->getAttribute('grid'), $request->getHeaderLine('User-Agent'));
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}

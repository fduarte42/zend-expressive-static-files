<?php

declare(strict_types=1);

namespace Fduarte42\StaticFiles;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Stratigility\MiddlewarePipe;

class StaticFilesMiddlewarePipe implements MiddlewareInterface
{
    /** @var MiddlewarePipe */
    protected $pipeline;

    public function __construct(MiddlewarePipe $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface
    {
        return $this->pipeline->process($request, $handler);
    }
}

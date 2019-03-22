<?php

namespace Fduarte42\StaticFilesTest;

require_once __DIR__ . '/../src/ContentTypes.php';
require_once __DIR__ . '/../src/ContentTypes.php';

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fduarte42\StaticFiles\StaticFilesMiddlewarePipeFactory;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
use Zend\Stratigility\MiddlewarePipeInterface;

class StaticFilesMiddlewarePipeTest extends TestCase
{

    public function testReturnsFileContentAndProperHeadersWhenFileExistsAndIsValid()
    {
        /** @var ContainerInterface|MockObject $container */
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $container->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn([
                'static_files' => [
                    '/' => [
                        'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                    ]
                ]
            ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/test.json', 'GET');

        $responseFromDelegate = new Response();

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler
            ->expects($this->never())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $middlewarePipe->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/public-test/test.json');

        $responseFromUnit->getBody()->rewind();
        $this->assertEquals(
            $expectedFileContents,
            $responseFromUnit->getBody()->getContents()
        );

        $this->assertEquals(
            $responseFromUnit->getHeaders(),
            ['content-type' => ['application/json']]
        );
    }

    public function testSkipsMiddlewareIfPathDoesNotMatch()
    {
        /** @var ContainerInterface|MockObject $container */
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $container->expects($this->once())
            ->method('has')
            ->with('config')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn([
                'static_files' => [
                    '/files' => [
                        'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                    ]
                ]
            ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/other', 'GET');

        $responseFromDelegate = new Response('Not found', 404);

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler
            ->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        $pathMiddleware = new PathMiddlewareDecorator('/files', $middlewarePipe);
        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $pathMiddleware->process($request, $mockRequestHandler);

        $this->assertEquals(
            $responseFromUnit->getStatusCode(),
            404
        );
    }

}

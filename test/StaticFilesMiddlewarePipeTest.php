<?php
declare(strict_types=1);

namespace Fduarte42\StaticFilesTest;

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
                'serve_static' => [
                    '/' => [
                        'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                    ]
                ]
            ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/test.json', 'GET');

        $responseFromDelegate = new Response\EmptyResponse();

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
                'serve_static' => [
                    '/files' => [
                        'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                    ]
                ]
            ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/other', 'GET');

        $responseFromDelegate = new Response\TextResponse('Not found', 404);

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

    public function testSkipsMiddlewareIfFileExistsInPublicCache()
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
                             'serve_static' => [
                                 '/' => [
                                     'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                                     'publicCachePath' => __DIR__ . '/files-cache',
                                 ]
                             ]
                         ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/test.json', 'GET');

        $responseFromDelegate = new Response\EmptyResponse();

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler
            //->expects($this->never())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $middlewarePipe->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/files-cache/test.json');

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

    public function testCreatesSymLinkInPublicCache()
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
                'serve_static' => [
                    '/' => [
                        'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                        'publicCachePath' => __DIR__ . '/empty-cache',
                    ]
                ]
            ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/test.json', 'GET');

        $responseFromDelegate = new Response\EmptyResponse();

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler
            //->expects($this->never())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $middlewarePipe->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/empty-cache/test.json');

        $responseFromUnit->getBody()->rewind();
        $this->assertEquals(
            $expectedFileContents,
            $responseFromUnit->getBody()->getContents()
        );

        $this->assertEquals(
            $responseFromUnit->getHeaders(),
            ['content-type' => ['application/json']]
        );

        unlink(__DIR__ . '/empty-cache/test.json');
    }


    public function testCreatesSymLinkInPublicCacheWithAbsoluteRootPath()
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
                'serve_static' => [
                    '/' => [
                        'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                        'publicCachePath' => __DIR__ . '/empty-cache',
                        'rootPath' => __DIR__
                    ]
                ]
            ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/test.json', 'GET');

        $responseFromDelegate = new Response\EmptyResponse();

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler
            //->expects($this->never())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $middlewarePipe->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/empty-cache/test.json');

        $responseFromUnit->getBody()->rewind();
        $this->assertEquals(
            $expectedFileContents,
            $responseFromUnit->getBody()->getContents()
        );

        $this->assertEquals(
            $responseFromUnit->getHeaders(),
            ['content-type' => ['application/json']]
        );

        unlink(__DIR__ . '/empty-cache/test.json');
    }

    public function testCreatesSymLinkInPublicCacheWithSubDir()
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
                             'serve_static' => [
                                 '/' => [
                                     'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                                     'publicCachePath' => __DIR__ . '/empty-cache',
                                 ]
                             ]
                         ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/subdir/test.json', 'GET');

        $responseFromDelegate = new Response\EmptyResponse();

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler
            //->expects($this->never())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $middlewarePipe->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/empty-cache/subdir/test.json');

        $responseFromUnit->getBody()->rewind();
        $this->assertEquals(
            $expectedFileContents,
            $responseFromUnit->getBody()->getContents()
        );

        $this->assertEquals(
            $responseFromUnit->getHeaders(),
            ['content-type' => ['application/json']]
        );

        unlink(__DIR__ . '/empty-cache/subdir/test.json');
        rmdir(__DIR__ . '/empty-cache/subdir');
    }

    public function testCreatesSymLinkInPublicCacheWithAbsolutRootPathAndSubDir()
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
                             'serve_static' => [
                                 '/' => [
                                     'fileSystemAssetDirectory' => __DIR__ . '/public-test',
                                     'publicCachePath' => __DIR__ . '/empty-cache',
                                     'rootPath' => __DIR__
                                 ]
                             ]
                         ]);

        /** @var MiddlewarePipeInterface $middlewarePipe */
        $middlewarePipe = (new StaticFilesMiddlewarePipeFactory)($container);
        $request = new ServerRequest([], [], 'https://example.com/subdir/test.json', 'GET');

        $responseFromDelegate = new Response\EmptyResponse();

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler
            //->expects($this->never())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $middlewarePipe->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/empty-cache/subdir/test.json');

        $responseFromUnit->getBody()->rewind();
        $this->assertEquals(
            $expectedFileContents,
            $responseFromUnit->getBody()->getContents()
        );

        $this->assertEquals(
            $responseFromUnit->getHeaders(),
            ['content-type' => ['application/json']]
        );

        unlink(__DIR__ . '/empty-cache/subdir/test.json');
        rmdir(__DIR__ . '/empty-cache/subdir');
    }
}

<?php

namespace Reliv\ServeStaticTest;

require_once __DIR__ . '/../src/ContentTypes.php';
require_once __DIR__ . '/../src/ContentTypes.php';

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Reliv\ServeStatic\ServeStaticMiddleware;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class ServeStaticMiddlewareTest extends TestCase
{
    public function testRefusesToReturnFileThatIsOutsideTheAssetDirectoryForSecurity()
    {
        $unit = new ServeStaticMiddleware(__DIR__ . '/public-test');
        $request = new ServerRequest([], [], 'https://example.com/../secrets.php', 'GET');
        $responseFromDelegate = new Response();

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $mockRequestHandler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($responseFromDelegate);

        $responseFromUnit = $unit->process($request, $mockRequestHandler);

        $this->assertTrue($responseFromDelegate === $responseFromUnit);
    }

    public function testReturnsFileContentAndProperHeadersWhenFileExistsAndIsValid()
    {
        $unit = new ServeStaticMiddleware(__DIR__ . '/public-test');
        $request = new ServerRequest([], [], 'https://example.com/test.json', 'GET');

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $unit->process($request, $mockRequestHandler);

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

    public function testMultipleAssetDirectories()
    {
        $filesSystemAssetDirectories = [
            __DIR__ . '/public-test2',
            __DIR__ . '/public-test'
        ];

        $unit = new ServeStaticMiddleware($filesSystemAssetDirectories);
        $request = new ServerRequest([], [], 'https://example.com/test2.json', 'GET');

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $unit->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/public-test2/test2.json');

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

    public function testMultipleAssetDirectoriesWithOverride()
    {
        $filesSystemAssetDirectories = [
            __DIR__ . '/public-test',
            __DIR__ . '/public-test2',
        ];

        $unit = new ServeStaticMiddleware($filesSystemAssetDirectories);
        $request = new ServerRequest([], [], 'https://example.com/test.json', 'GET');

        /** @var RequestHandlerInterface|MockObject $mockRequestHandler */
        $mockRequestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();

        /**
         * @var $responseFromUnit ResponseInterface
         */
        $responseFromUnit = $unit->process($request, $mockRequestHandler);

        $expectedFileContents = file_get_contents(__DIR__ . '/public-test2/test.json');

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
}

<?php

declare(strict_types=1);

namespace Reliv\ServeStatic;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use SplDoublyLinkedList;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class ServeStaticMiddleware implements MiddlewareInterface
{
    /** @var \SplStack */
    protected $fileSystemAssetDirectoryStack;

    /** @var array */
    protected $options;

    /**
     * ServeStaticMiddleware constructor.
     * @param array|string $fileSystemAssetDirectories
     * @param array $options
     */
    public function __construct($fileSystemAssetDirectories, array $options = [])
    {
        $fileSystemAssetDirectories = is_array($fileSystemAssetDirectories) ? $fileSystemAssetDirectories : [$fileSystemAssetDirectories];

        $this->options = array_merge(
            [
                'publicCachePath' => null,
                'headers' => [
                    //headerKey => headerValue
                ]
            ],
            $options
        );

        if (!array_key_exists('contentTypes', $this->options)) {
            $this->options['contentTypes'] = ContentTypes::DEFAULT_CONTENT_TYPES;
        }

        $this->fileSystemAssetDirectoryStack = new \SplStack();
        $this->fileSystemAssetDirectoryStack->setIteratorMode(SplDoublyLinkedList::IT_MODE_KEEP);

        foreach ($fileSystemAssetDirectories as $fileSystemAssetDirectory) {
            $this->fileSystemAssetDirectoryStack->push($fileSystemAssetDirectory);
        }
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
        $uriSubPath = $request->getUri()->getPath();

        // Ensure we have been given a file path to look for
        if (empty($uriSubPath)) {
            return $handler->handle($request);
        }


        foreach ($this->fileSystemAssetDirectoryStack as $fileSystemAssetDirectory) {
            // Build filePath
            $filePath = realpath($fileSystemAssetDirectory . $uriSubPath);

            // Check for invalid path
            if ($filePath == false) {
                // look for file in next directory
                continue;
            }

            // Ensure someone isn't using dots to go backward past the asset root folder
            if (!strpos($filePath, realpath($fileSystemAssetDirectory)) === 0) {
                return $handler->handle($request);
            }

            // Ensure the file exists and is not a directory
            if (!is_file($filePath)) {
                // look for file in next directory
                continue;
            }

            // Write to publicCachePath if configured
            if ($this->options['publicCachePath'] !== null) {
                $writePath = $this->options['publicCachePath'] . $uriSubPath;
                $writeDir = dirname($writePath);
                if (!is_dir($writeDir)) {
                    mkdir($writeDir, 0777, true);
                }
                copy($filePath, $writePath);
            }

            // Build response as stream
            $body = new Stream($filePath);
            $response = new Response();
            $response = $response->withBody($body);

            // Add content type if known
            $extension = pathinfo($filePath)['extension'];
            if (array_key_exists($extension, $this->options['contentTypes'])) {
                $response = $response->withHeader('content-type', $this->options['contentTypes'][$extension]);
            }

            // Add additional configured headers
            foreach ($this->options['headers'] as $key => $value) {
                $response = $response->withHeader($key, $value);
            }

            return $response;
        }

        return $handler->handle($request);
    }
}

<?php

declare(strict_types=1);

namespace Fduarte42\StaticFiles;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplStack;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class StaticFilesMiddleware implements MiddlewareInterface
{
    /** @var SplStack */
    protected $fileSystemAssetDirectoriesStack;

    /** @var array */
    protected $options;

    /**
     * StaticFilesMiddleware constructor.
     * @param array|string $fileSystemAssetDirectories
     * @param array $options
     */
    public function __construct($fileSystemAssetDirectories, array $options = [])
    {
        $fileSystemAssetDirectories = is_array($fileSystemAssetDirectories) ? $fileSystemAssetDirectories : [$fileSystemAssetDirectories];

        $this->fileSystemAssetDirectoriesStack = new SplStack();

        foreach ($fileSystemAssetDirectories as $fileSystemAssetDirectory) {
            $this->fileSystemAssetDirectoriesStack->push($fileSystemAssetDirectory);
        }

        $this->options = array_merge(
            [
                'publicCachePath' => null,
                'rootPath' => '..',
                'headers' => [
                    //headerKey => headerValue
                ],
                'ignoredExtensions' => [
                    'php',
                    'phtml',
                ],
            ],
            $options
        );

        if (!array_key_exists('contentTypes', $this->options)) {
            $this->options['contentTypes'] = ContentTypes::DEFAULT_CONTENT_TYPES;
        }
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws Exception
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface
    {
        $uriSubPath = $request->getUri()->getPath();

        if (in_array(pathinfo($uriSubPath)['extension'] ?? '', $this->options['ignoredExtensions'])) {
            return $handler->handle($request);
        }

        // Ensure we have been given a file path to look for
        if (empty($uriSubPath)) {
            return $handler->handle($request);
        }


        foreach ($this->fileSystemAssetDirectoriesStack as $fileSystemAssetDirectory) {
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

            // create symlink in publicCachePath if configured
            if ($this->options['publicCachePath'] !== null) {
                // do not overwrite file in public cache
                if (file_exists($this->options['publicCachePath'] . $uriSubPath)) {
                    $filePath = $this->options['publicCachePath'] . $uriSubPath;
                } else {
                    // create lock file
                    $lockfile = $this->options['publicCachePath'] . '/' . md5($uriSubPath) . '.lock';
                    $lock = fopen($lockfile, 'c');

                    // try to aquire exclusive lock
                    if (flock($lock, LOCK_EX)) {
                        $writePath = $this->options['publicCachePath'] . $uriSubPath;
                        $writeDir = dirname($writePath);
                        if (!is_dir($writeDir)) {
                            mkdir($writeDir, 0777, true);
                        }

                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                            // don't use symlinks on windows
                            copy($filePath, $writePath);
                        } else {
                            // check if rootPath is absolute
                            if ($this->options['rootPath'][0] === '/') {
                                $link = str_replace(
                                    realpath(rtrim($this->options['rootPath'], '/')) . '/',
                                    str_repeat('../', substr_count($uriSubPath, '/')),
                                    $filePath
                                );
                            } else {
                                $link = str_replace(
                                    rtrim(
                                        realpath(
                                            rtrim(
                                                $this->options['publicCachePath'] . '/' . $this->options['rootPath'],
                                                '/'
                                            )
                                        ),
                                        '/'
                                    ) . '/',
                                    str_repeat('../', substr_count($uriSubPath, '/')),
                                    $filePath
                                );
                            }
                            symlink($link, $writePath);
                        }

                        // unlock and remove lock file
                        flock($lock, LOCK_UN);
                        fclose($lock);
                        @unlink($lockfile);
                    } else {
                        throw new Exception('could not aquire file lock in publicCachePath');
                    }
                }
            }

            // Build response as stream
            $body = new Stream($filePath);
            $response = new Response($body);

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

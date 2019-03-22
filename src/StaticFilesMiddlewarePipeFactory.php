<?php

declare(strict_types=1);

namespace Fduarte42\StaticFiles;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Zend\Stratigility\MiddlewarePipe;
use function Zend\Stratigility\path;

class StaticFilesMiddlewarePipeFactory
{
    /**
     * Load config and instantiate middleware
     *
     * Example config:
     * 'static_files' => [
     *      '/fun-module/assets' => [
     *          'fileSystemAssetDirectory' => [
     *                  __DIR__ . '/../vendor/fund-module/public'
     *          ],
     *          'publicCachePath' => __DIR__ . '/../public/fun-module/assets',
     *          'headers' => [],
     *      ]
     *  ]
     *
     * @param ContainerInterface $container
     *
     * @return MiddlewareInterface
     */
    public function __invoke(ContainerInterface $container): MiddlewareInterface
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = isset($config['static_files']) ? $config['static_files'] : [];

        $middlewarePipe = new MiddlewarePipe();
        foreach ($config as $uriPath => $options) {
            if (!array_key_exists('fileSystemAssetDirectory', $options)) {
                throw new \InvalidArgumentException('key "fileSystemAssetDirectory" missing in config');
            }

            $fileSystemAssetDirectory = $options['fileSystemAssetDirectory'];
            unset($options['fileSystemAssetDirectory']);

            $middlewarePipe->pipe(path($uriPath, new StaticFilesMiddleware(
                $fileSystemAssetDirectory,
                $options
            )));
        }

        $middleware = new StaticFilesMiddlewarePipe($middlewarePipe);

        return $middleware;
    }
}
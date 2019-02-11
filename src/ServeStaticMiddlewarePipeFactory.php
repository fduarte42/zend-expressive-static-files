<?php

declare(strict_types=1);

namespace Reliv\ServeStatic;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Zend\Stratigility\MiddlewarePipe;
use function Zend\Stratigility\path;

class ServeStaticMiddlewarePipeFactory
{
    /**
     * Load config and instantiate middleware
     *
     * Example config:
     * 'serve_static' => [
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
        $config = isset($config['serve_static']) ? $config['serve_static'] : [];

        $middlewarePipe = new MiddlewarePipe();
        foreach ($config as $uriPath => $options) {
            if (!array_key_exists('fileSystemAssetDirectory', $options)) {
                throw new \InvalidArgumentException('key "fileSystemAssetDirectory" missing in config');
            }

            $fileSystemAssetDirectory = $options['fileSystemAssetDirectory'];
            unset($options['fileSystemAssetDirectory']);

            $middlewarePipe->pipe(path($uriPath, new ServeStaticMiddleware(
                $fileSystemAssetDirectory,
                $options
            )));
        }

        $middleware = new ServeStaticMiddlewarePipe($middlewarePipe);

        return $middleware;
    }
}

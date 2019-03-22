<?php

namespace Fduarte42\StaticFiles;


class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'middleware_pipeline' => $this->getMiddlewarePipeline(),
        ];
    }


    public function getDependencies(): array
    {
        return [
            'factories' => [
                'serve-static-middleware-pipe' => StaticFilesMiddlewarePipeFactory::class,
            ],
        ];
    }


    public function getMiddlewarePipeline()
    {
        return [
            [
                'middleware' => 'serve-static-middleware-pipe',
                'priority' => 1000,
            ],
        ];
    }


}
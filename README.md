# Zend Expressive Static Files [![Build Status](https://travis-ci.com/fduarte42/zend-expressive-static-files.svg?branch=master)](https://travis-ci.com/fduarte42/zend-expressive-static-files)
A PSR-15 middleware that serves static assets for you

Example usage:
```php
$app->pipe('/fun-module/assets', new \Fduarte42\StaticFiles\StaticFilesMiddleware(
    __DIR__ . '/../vendor/fund-module/public',
    ['publicCachePath' => __DIR__ . '/../public/fun-module/assets']
));
```

This is a fork of Serve Static https://github.com/reliv/serve-static

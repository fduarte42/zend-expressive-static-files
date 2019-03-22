# Zend Expressive Static Files
A PSR-15 middleware that serves static assets for you

Example usage:
```php
$app->pipe('/fun-module/assets', new \Fduarte42\StaticFiles\StaticFilesMiddleware(
    __DIR__ . '/../vendor/fund-module/public',
    ['publicCachePath' => __DIR__ . '/../public/fun-module/assets']
));
```

This is a fork of Serve Static https://github.com/reliv/serve-static

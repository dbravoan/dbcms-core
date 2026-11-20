<?php

declare(strict_types=1);

// Laravel's ServeCommand selects this project router for artisan serve and Sail.
// Only public compiled assets can be read by an opaque editorial preview frame.
$publicPath = (string) getcwd();
$uri = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''));
if (str_starts_with($uri, '/build/assets/')) {
    clearstatcache(true);
    $file = str_contains($uri, "\0") ? false : realpath($publicPath.$uri);
    $mime = match (strtolower(pathinfo($file ?: $uri, PATHINFO_EXTENSION))) {
        'js', 'mjs' => 'application/javascript',
        'css' => 'text/css',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'json' => 'application/json',
        'wasm' => 'application/wasm',
        default => null,
    };
    if ($file === false || ! is_file($file) || ! str_starts_with($file, $publicPath.'/build/assets/') || $mime === null) {
        http_response_code(404);

        return true;
    }
    if (! in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');

        return true;
    }
    header('Content-Type: '.$mime);
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        readfile($file);
    }

    return true;
}

return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';

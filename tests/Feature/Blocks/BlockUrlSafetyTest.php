<?php

declare(strict_types=1);

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use Illuminate\Validation\ValidationException;

test('block url fields reject executable protocols through shared validation', function (string $type, array $payload): void {
    app(BlockRegistryInterface::class)->validate($type, $payload);
})->with([
    ['button', ['url' => 'javascript:alert(1)']],
    ['button', ['url' => "java\nscript:alert(1)"]],
    ['button', ['url' => 'data:text/html,<script>alert(1)</script>']],
    ['image', ['image_url' => 'javascript:alert(1)']],
    ['carousel', ['items' => [['children' => [['type' => 'image', 'image_url' => 'javascript:alert(1)']]]]]],
    ['video', ['poster_url' => 'data:image/svg+xml,<svg onload=alert(1)>']],
    ['before_after', ['image_url' => 'javascript:alert(1)']],
])->throws(ValidationException::class);

test('ordinary local and external media urls remain usable', function (string $url): void {
    $registry = app(BlockRegistryInterface::class);
    $registry->validate('image', ['image_url' => $url]);
    expect($registry->render('image', ['image_url' => $url, 'image_alt' => 'Hero']))->toContain('img');
})->with(['/storage/hero.webp', 'https://example.test/hero.webp']);

test('stored unsafe block urls do not reach public links', function (string $type, array $payload): void {
    $html = app(BlockRegistryInterface::class)->render($type, $payload);
    expect($html)->not->toContain('javascript:', 'data:text/html');
})->with([
    ['button', ['label' => 'Action', 'url' => 'javascript:alert(1)']],
    ['button', ['label' => 'Action', 'url' => 'data:text/html,active']],
    ['image', ['image_url' => 'javascript:alert(1)', 'image_alt' => 'Hero']],
    ['video', ['video_url' => '/clip.mp4', 'poster_url' => 'data:image/svg+xml,<svg onload=alert(1)>']],
    ['before_after', ['title' => 'Comparison', 'image_url' => 'javascript:alert(1)', 'after_image_url' => 'javascript:alert(2)']],
]);

test('ordinary local and external action urls remain usable', function (string $url): void {
    $registry = app(BlockRegistryInterface::class);
    $registry->validate('button', ['url' => $url]);
    expect($registry->render('button', ['label' => 'Action', 'url' => $url]))->toContain('href=');
})->with(['/contact', '#details', 'https://example.test/path', 'mailto:hello@example.test', 'tel:+34123456789']);

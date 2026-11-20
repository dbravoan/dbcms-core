<?php

declare(strict_types=1);

use DbCms\Core\Services\Media\SvgSanitizer;

test('it strips scripts, event handlers and javascript urls', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
        .'<script>alert(2)</script>'
        .'<rect width="10" height="10" fill="red"/>'
        .'<a href="javascript:alert(3)"><text>x</text></a>'
        .'</svg>';

    $sanitised = (new SvgSanitizer)->sanitize($svg);

    expect($sanitised)
        ->not->toContain('<script')
        ->not->toContain('onload')
        ->not->toContain('javascript:')
        ->toContain('<rect');
});

test('it keeps a benign svg drawing intact', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="4"/></svg>';

    expect((new SvgSanitizer)->sanitize($svg))->toContain('<circle');
});

test('it removes embedded foreign objects and iframes', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
        .'<foreignObject><body xmlns="http://www.w3.org/1999/xhtml">x</body></foreignObject>'
        .'<iframe src="https://evil.test"></iframe>'
        .'<rect/>'
        .'</svg>';

    $sanitised = (new SvgSanitizer)->sanitize($svg);

    expect($sanitised)->not->toContain('foreignObject')->not->toContain('<iframe')->toContain('<rect');
});

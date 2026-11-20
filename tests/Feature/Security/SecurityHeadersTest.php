<?php

declare(strict_types=1);

test('public web responses carry baseline security headers', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain('strict-dynamic')
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'");
});

test('HSTS is only advertised over HTTPS', function (): void {
    $this->get('/admin/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/admin/login')->assertHeader('Strict-Transport-Security');
});

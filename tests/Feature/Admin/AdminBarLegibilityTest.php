<?php

declare(strict_types=1);
use DbCms\Core\Cms;

/**
 * Backoffice surfaces that are dragged onto the frontoffice (the admin bar and
 * its SEO/GEO audit drawer) must stay legible in **both** backoffice themes.
 *
 * They are injected over the front themes, so the backoffice dark palette does
 * not apply and neither does `fixture-admin.css`. Every surface therefore paints
 * itself with the `--admin-bar-*` tokens: this suite guards the contract.
 */
beforeEach(function (): void {
    $this->db = file_get_contents(Cms::path('resources/admin-theme/assets/admin-bar.css'));
    $this->bar = file_get_contents(Cms::path('resources/views/components/frontoffice-admin-bar.blade.php'));
});

test('both admin-bar themes declare the alert surfaces', function (): void {
    $tokens = [
        '--admin-bar-alert-warning-bg',
        '--admin-bar-alert-warning-border',
        '--admin-bar-alert-warning-text',
        '--admin-bar-alert-success-bg',
        '--admin-bar-alert-success-text',
        '--admin-bar-alert-danger-bg',
        '--admin-bar-alert-danger-text',
    ];

    foreach ($tokens as $token) {
        expect($this->db)->toContain($token);
    }
});

test('the bar paints every legacy admin class so nothing renders unstyled', function (): void {
    foreach ([
        'admin-card',
        'admin-text-primary',
        'admin-text-muted',
        'admin-score-val',
        'admin-icon-box',
        'admin-alert',
        'admin-alert-success',
        'admin-alert-warning',
        'admin-btn-primary',
        'admin-btn-secondary',
    ] as $class) {
        expect($this->bar)->toContain('.'.$class);
    }
});

test('the audit tips use the semantic alert instead of a low contrast utility', function (): void {
    expect($this->bar)->toContain('admin-alert admin-alert-warning')
        ->and($this->bar)->not->toContain('bg-amber-500/10 border border-amber-500/30 text-amber-200');
});

test('the edit button keeps contrast on hover', function (): void {
    // El hover debe fijar fondo Y texto juntos; antes heredaba utilidades que en
    // el tema claro dejaban acento sobre acento y el botón desaparecía.
    expect($this->bar)->toContain('.admin-bar-root .admin-bar-btn-primary:hover')
        ->and($this->bar)->toContain('color: var(--admin-bar-accent-contrast) !important');
});

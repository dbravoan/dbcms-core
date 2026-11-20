<?php

declare(strict_types=1);

use DbCms\Core\Services\System\FrameworkSupportService;

it('reports annual Laravel support without claiming LTS or a vulnerability audit', function (): void {
    $status = (new FrameworkSupportService)->statusForVersion('13.32.0', new DateTimeImmutable('2026-10-10'));
    expect($status['bug_fixes_until'])->toBe('Q3 2027')
        ->and($status['security_fixes_until'])->toBe('2028-03-17')
        ->and($status['support_active'])->toBeTrue()
        ->and($status['security_audit'])->toBe('not_run');
});

it('preserves uncertainty throughout the published bug-fix quarter', function (string $date, ?bool $active): void {
    $status = (new FrameworkSupportService)->statusForVersion('13.32.0', new DateTimeImmutable($date));

    expect($status['bug_fixes_until'])->toBe('Q3 2027')
        ->and($status['bug_fixes_active'])->toBe($active)
        ->and($status['support_active'])->toBeTrue();
    if ($active === null) {
        expect($status['recommendation'])->toContain('published only as Q3 2027')
            ->not->toContain('Security fixes only');
    }
})->with([
    'before the quarter' => ['2027-06-30', true],
    'first day of the quarter' => ['2027-07-01', null],
    'previously inferred day' => ['2027-09-17', null],
    'last day of the quarter' => ['2027-09-30', null],
    'after the quarter' => ['2027-10-01', false],
]);

it('retains exact published support deadlines', function (string $version, string $date, string $field, bool $active): void {
    $status = (new FrameworkSupportService)->statusForVersion($version, new DateTimeImmutable($date));

    expect($status[$field])->toBe($active);
})->with([
    'Laravel 12 last bug-fix day' => ['12.1.0', '2026-08-13', 'bug_fixes_active', true],
    'Laravel 12 after bug fixes' => ['12.1.0', '2026-08-14', 'bug_fixes_active', false],
    'Laravel 13 last security day' => ['13.32.0', '2028-03-17', 'support_active', true],
    'Laravel 13 after security fixes' => ['13.32.0', '2028-03-18', 'support_active', false],
]);

it('uses the installed major version support window and rejects expired or unknown branches', function (): void {
    $monitor = new FrameworkSupportService;
    expect($monitor->statusForVersion('11.99.0', new DateTimeImmutable('2026-10-10'))['support_active'])->toBeFalse()
        ->and($monitor->statusForVersion('12.1.0', new DateTimeImmutable('2026-10-10'))['bug_fixes_active'])->toBeFalse()
        ->and($monitor->statusForVersion('99.0.0', new DateTimeImmutable('2026-10-10'))['support_active'])->toBeFalse();
});

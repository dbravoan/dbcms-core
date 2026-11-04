<?php

declare(strict_types=1);

namespace DbCms\Core\Services\System;

use DateTimeImmutable;

final class FrameworkSupportService
{
    /** @var array<int, array{string, string}> */
    private const SUPPORT_WINDOWS = [
        11 => ['2025-09-03', '2026-03-12'],
        12 => ['2026-08-13', '2027-02-24'],
        13 => ['Q3 2027', '2028-03-17'],
    ];

    /**
     * Laravel releases annually: 18 months of bug fixes and two years of security fixes.
     * Preserve the precision published at https://github.com/laravel/docs/blob/13.x/releases.md.
     *
     * @return array{current_version: string, support_active: bool, bug_fixes_active: ?bool, bug_fixes_until: ?string, security_fixes_until: ?string, security_audit: string, recommendation: string}
     */
    public function statusForVersion(string $version, DateTimeImmutable $today): array
    {
        $window = self::SUPPORT_WINDOWS[(int) explode('.', $version)[0]] ?? null;
        $date = $today->format('Y-m-d');
        $securityActive = $window !== null && $date <= $window[1];
        $bugFixesActive = $window !== null && $date <= $window[0];
        if ($window !== null && preg_match('/^Q([1-4]) (\d{4})$/D', $window[0], $quarter) === 1) {
            $start = new DateTimeImmutable(sprintf('%s-%02d-01', $quarter[2], ((int) $quarter[1] - 1) * 3 + 1));
            $bugFixesActive = $date < $start->format('Y-m-d') ? true : ($date >= $start->modify('+3 months')->format('Y-m-d') ? false : null);
        }
        $recommendation = match (true) {
            $window === null => 'Unknown support window: verify the official release policy.',
            ! $securityActive => 'Security support expired; upgrade to a supported release.',
            $bugFixesActive === null => 'Security support is active; the bug-fix end date is published only as '.$window[0].'. Verify the official release policy.',
            $bugFixesActive => 'Annual Laravel release within its bug-fix and security support windows.',
            default => 'Security fixes only; plan an upgrade to a branch receiving bug fixes.',
        };

        return [
            'current_version' => $version,
            'support_active' => $securityActive,
            'bug_fixes_active' => $bugFixesActive,
            'bug_fixes_until' => $window[0] ?? null,
            'security_fixes_until' => $window[1] ?? null,
            'security_audit' => 'not_run',
            'recommendation' => $recommendation,
        ];
    }
}

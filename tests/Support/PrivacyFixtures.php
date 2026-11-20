<?php

declare(strict_types=1);

namespace Tests\Support;

final class PrivacyFixtures
{
    /**
     * @param  list<string>  $modules
     * @param  list<string>  $additionalOwners
     * @return list<array<string,mixed>>
     */
    public static function processingFor(array $modules, array $additionalOwners = []): array
    {
        $owners = ['core', 'forms', 'executions', ...$additionalOwners];
        foreach (['blog' => 'blog_comments', 'content_audit' => 'content_audit', 'analytics' => 'analytics'] as $module => $owner) {
            if (in_array($module, $modules, true)) {
                $owners[] = $owner;
            }
        }
        $owners = array_values(array_unique($owners));

        return array_map(static fn (string $owner): array => [
            'owner' => $owner, 'purpose' => 'Respond to the requested test service', 'legal_basis' => 'Requested pre-contractual steps',
            'data' => 'Provided contact and request details', 'retention' => '30 days after the last recorded order activity, or submission for forms and inquiries',
            'retention_days' => 30, 'recipients' => 'Test controller and test hosting provider', 'transfers' => 'No international transfers in this test fixture',
        ], $owners);
    }

    /** @return array<string,mixed> */
    public static function company(): array
    {
        return [
            'company_name' => 'Example Test SL', 'tax_id' => 'TEST-TAX-ID', 'address' => 'Test Street 1, 28001 Madrid', 'country' => 'ES',
            'contact_email' => 'contact@example.test', 'privacy_email' => 'privacy@example.test', 'activity' => 'Software test services',
            'hosting_provider' => 'Test hosting provider', 'hosting_country' => 'ES', 'processing' => [[
                'owner' => 'core', 'purpose' => 'Technical delivery of the requested website', 'legal_basis' => 'Legitimate interest in technical security',
                'data' => 'Necessary session and security data', 'retention' => 'Sessions for their configured lifetime; security records and notifications for 30 days', 'retention_days' => 30,
                'recipients' => 'Test hosting provider', 'transfers' => 'None in this fixture',
            ], ...array_values(array_filter(self::processingFor([]), static fn (array $entry): bool => $entry['owner'] !== 'core'))], 'reviewed' => true,
        ];
    }
}

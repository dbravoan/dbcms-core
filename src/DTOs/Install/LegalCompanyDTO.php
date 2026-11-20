<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Install;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class LegalCompanyDTO
{
    private const COUNTRIES = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    /** @param array<string,mixed> $values */
    private function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function fromArray(array $input): self
    {
        /** @var array<string,mixed> $values */
        $values = Validator::make($input, [
            'company_name' => ['required', 'string', 'max:180'],
            'commercial_activity' => ['sometimes', 'boolean'],
            'registry_required' => ['sometimes', 'boolean'],
            'dpo_required' => ['sometimes', 'boolean'],
            'tax_id' => [Rule::requiredIf(in_array($input['commercial_activity'] ?? false, [true, 1, '1'], true)), 'nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', Rule::in(explode(' ', self::COUNTRIES))],
            'contact_email' => ['required', 'email', 'max:255'],
            'privacy_email' => ['required', 'email', 'max:255'],
            'activity' => ['required', 'string', 'max:255'],
            'commercial_registry' => [Rule::requiredIf(in_array($input['registry_required'] ?? false, [true, 1, '1'], true)), 'nullable', 'string', 'max:500'],
            'dpo_contact' => [Rule::requiredIf(in_array($input['dpo_required'] ?? false, [true, 1, '1'], true)), 'nullable', 'string', 'max:500'],
            'hosting_provider' => ['required', 'string', 'max:255'],
            'hosting_country' => ['required', 'string', 'size:2', Rule::in(explode(' ', self::COUNTRIES))],
            'processing' => ['required', 'array', 'min:1'],
            'processing.*.owner' => ['required', 'string', 'regex:/^[a-z][a-z0-9_\-]*$/', 'max:80', 'distinct'],
            'processing.*.retention_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'processing.*.manual_review' => ['sometimes', 'boolean'],
            'processing.*.purpose' => ['required', 'string', 'max:1000'],
            'processing.*.legal_basis' => ['required', 'string', 'max:1000'],
            'processing.*.data' => ['required', 'string', 'max:1000'],
            'processing.*.retention' => ['required', 'string', 'max:1000'],
            'processing.*.recipients' => ['required', 'string', 'max:1000'],
            'processing.*.transfers' => ['required', 'string', 'max:1000'],
            'reviewed' => ['required', 'accepted'],
        ])->validate();
        unset($values['reviewed']);
        foreach (['commercial_activity', 'registry_required', 'dpo_required'] as $flag) {
            $values[$flag] = in_array($values[$flag] ?? false, [true, 1, '1'], true);
        }
        $values += ['tax_id' => '', 'commercial_registry' => '', 'dpo_contact' => ''];
        foreach ($values['processing'] as &$processing) {
            $processing['manual_review'] = in_array($processing['manual_review'] ?? false, [true, 1, '1'], true);
            if (isset($processing['retention_days'])) {
                $processing['retention_days'] = (int) $processing['retention_days'];
            }
        }
        unset($processing);
        foreach ($values['processing'] as $entry) {
            if ($entry['owner'] === 'accounts' && (! $entry['manual_review'] || isset($entry['retention_days']))) {
                throw ValidationException::withMessages(['processing' => 'Account retention requires a documented lifecycle criterion and manual_review, without automatic retention_days.']);
            }
        }
        $core = array_values(array_filter($values['processing'], static fn (array $entry): bool => $entry['owner'] === 'core'));
        if ($core === [] || (int) ($core[0]['retention_days'] ?? 0) < 1) {
            throw ValidationException::withMessages(['processing' => 'Declare the core security, session and notification processing with an explicit automatic retention in days.']);
        }

        return new self($values);
    }
}

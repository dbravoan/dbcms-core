<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Services\Privacy\PrivacyConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class PrivacyConfigureCommand extends Command
{
    protected $signature = 'privacy:configure {file : Reviewed company and processing JSON file} {--adopt-legal-pages= : Reviewed JSON map of notice/privacy/cookies to existing Page IDs}';

    protected $description = 'Validate and update factual legal information and provision missing legal pages';

    public function handle(PrivacyConfigurationService $configuration): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The company JSON file is not readable.');

            return self::FAILURE;
        }
        try {
            $values = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($values)) {
                throw new \InvalidArgumentException('Company JSON must be an object.');
            }
            $adoptions = [];
            $mapping = $this->option('adopt-legal-pages');
            if (is_string($mapping) && $mapping !== '') {
                if (! is_file($mapping) || ! is_readable($mapping)) {
                    throw new \InvalidArgumentException('The legal-page adoption JSON file is not readable.');
                }
                $adoptions = json_decode((string) file_get_contents($mapping), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($adoptions)) {
                    throw new \InvalidArgumentException('The adoption JSON must map legal roles to existing Page IDs.');
                }
            }
            $configuration->configure(LegalCompanyDTO::fromArray($values), adoptions: $adoptions);
        } catch (\JsonException|\InvalidArgumentException|\DomainException|\RuntimeException|ValidationException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info('Legal information updated. Adopted pages retain their IDs, URLs and stored sections; their public content now uses the registered legal Blade view. Consent revision has changed.');

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Pure;

use Composer\InstalledVersions;
use DbCms\Core\Extensions\ComposerPackages;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ComposerPackagesTest extends TestCase
{
    private string $directory;

    /** @var array<string, mixed> */
    private array $installed;

    protected function setUp(): void
    {
        $this->installed = InstalledVersions::getRawData();
        $this->directory = sys_get_temp_dir().'/dbcms-composer-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        InstalledVersions::reload($this->installed);
        foreach (glob($this->directory.'/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->directory.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            rmdir($directory);
        }
        rmdir($this->directory);
    }

    public function test_composer_identity_and_dependencies_are_authoritative_outside_vendor(): void
    {
        $this->package('acme/news', 'news', ['dbravoan/dbcms-core' => '^1.0']);
        file_put_contents($this->directory.'/news/module.json', json_encode([
            'version' => '1.0.0', 'requires' => ['missing' => '*'],
            'settings' => ['enabled' => ['type' => 'boolean']],
        ], JSON_THROW_ON_ERROR));

        $modules = (new ComposerPackages)->modules();

        self::assertSame('1.0.0', $modules['news']['version']);
        self::assertSame(['core' => '^1.0'], $modules['news']['requires']);
        self::assertSame('boolean', $modules['news']['settings']['enabled']['type']);
        self::assertSame($this->directory.'/news', $modules['news']['directory']);
    }

    public function test_two_composer_packages_cannot_claim_the_same_cms_identifier(): void
    {
        $this->package('acme/news', 'news');
        $this->package('other/news', 'news', [], 'other');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Duplicate dbcms-module identifier');
        (new ComposerPackages)->modules();
    }

    /** @param array<string, string> $require */
    private function package(string $name, string $identifier, array $require = [], ?string $folder = null): void
    {
        $directory = $this->directory.'/'.($folder ?? $identifier);
        mkdir($directory);
        file_put_contents($directory.'/composer.json', json_encode([
            'name' => $name, 'require' => $require,
            'extra' => ['dbcms' => ['identifier' => $identifier, 'provider' => 'Acme\\News\\Provider']],
        ], JSON_THROW_ON_ERROR));
        $data = InstalledVersions::getRawData();
        $data['versions'][$name] = [
            'type' => 'dbcms-module', 'version' => '1.0.0.0', 'pretty_version' => '1.0.0',
            'install_path' => $directory, 'aliases' => [], 'dev_requirement' => false,
        ];
        InstalledVersions::reload($data);
    }
}

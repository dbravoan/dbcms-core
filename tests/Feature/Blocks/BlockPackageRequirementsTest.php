<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Types\HeadingBlock;
use DbCms\Core\Blocks\Types\RichTextBlock;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Validation\ValidationException;
use Tests\Support\ComposerPackageInventory;

test('availability reevaluates Composer core and module requirements after activation', function (string $requirement): void {
    $inventory = new ComposerPackageInventory;
    $packageName = $requirement === 'core' ? 'dbravoan/dbcms-core' : 'acme/optional-owner';
    if ($requirement !== 'core') {
        $inventory->add($packageName, 'dbcms-module', [
            'identifier' => $requirement, 'name' => 'Optional owner', 'provider' => 'Acme\\Owner\\Provider',
        ]);
        CmsModule::create([
            'identifier' => $requirement, 'name' => 'Optional owner', 'provider' => 'Acme\\Owner\\Provider',
            'version' => '1.0.0', 'is_active' => true, 'is_installed' => true,
        ]);
    }
    $inventory->add('acme/visual', 'dbcms-block-package', [
        'identifier' => 'example', 'name' => 'Example', 'blocks' => [HeadingBlock::class],
    ], [$packageName => $requirement === 'core' ? '~0.0.1' : '^1.0']);
    app()->instance(ComposerPackages::class, new ComposerPackages);
    app()->forgetInstance(ModuleManager::class);
    $manager = new BlockPackageManager(app(ComposerPackages::class));
    try {
        $manager->setEnabled('example', true);
        expect($manager->isEnabled('example'))->toBeTrue();
        if ($requirement === 'core') {
            config(['cms.version' => '5.0.0']);
        } else {
            CmsModule::where('identifier', $requirement)->update(['is_active' => false]);
        }
        expect($manager->isEnabled('example'))->toBeFalse();
        expect(fn () => $manager->setEnabled('example', true))->toThrow(ValidationException::class);
    } finally {
        $inventory->restore();
    }
})->with(['core', 'optional_owner']);

test('required schema fields are enforced at the root and inside repeaters', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $registry->register(new class extends RichTextBlock
    {
        public function getType(): string
        {
            return 'required-fields';
        }

        public function getEditorSchema(): array
        {
            return [
                'title' => ['type' => 'text', 'required' => true, 'rules' => 'string|max:40'],
                'items' => ['type' => 'repeater', 'fields' => ['value' => ['type' => 'number', 'required' => true]]],
            ];
        }
    });
    expect(fn () => $registry->validate('required-fields', []))->toThrow(ValidationException::class)
        ->and(fn () => $registry->validate('required-fields', ['title' => '', 'items' => []]))->toThrow(ValidationException::class)
        ->and(fn () => $registry->validate('required-fields', ['title' => 'Valid', 'items' => [[]]]))->toThrow(ValidationException::class);
    $registry->validate('required-fields', ['title' => 'Valid', 'items' => [['value' => 0]]]);
});

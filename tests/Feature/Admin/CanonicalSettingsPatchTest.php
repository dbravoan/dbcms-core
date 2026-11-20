<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CapabilityExecutor;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\Setting;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Cms\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class CanonicalSettingsPatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role);
        app(GeneralSettingsServiceInterface::class)->setMany([
            'general.site_name' => 'Before',
            'contact.email' => 'kept@example.test',
            'front.nav_toggle_enabled' => true,
        ]);
    }

    public function test_capability_patches_only_explicit_canonical_keys(): void
    {
        $result = app(CapabilityExecutor::class)->execute('cms_settings_update', [
            'attributes' => ['general.site_name' => 'After'],
        ], new ActorContext($this->admin->id), approved: true);

        $this->assertSame(['general.site_name'], $result['updated'] ?? null);
        $this->assertSame('After', setting('general.site_name'));
        $this->assertSame('kept@example.test', setting('contact.email'));
        $this->assertTrue(setting('front.nav_toggle_enabled'));
    }

    public function test_capability_rejects_unknown_keys_and_invalid_values_without_writes(): void
    {
        foreach ([['contact.email' => 'not-an-email'], ['unknown.setting' => 'value'], ['general_site_name' => 'old-format']] as $invalid) {
            $result = app(CapabilityExecutor::class)->execute('cms_settings_update', [
                'attributes' => ['general.site_name' => 'Must not persist'] + $invalid,
            ], new ActorContext($this->admin->id), approved: true);

            $this->assertArrayHasKey('validation', $result);
            $this->assertSame('Before', setting('general.site_name'));
            $this->assertSame('kept@example.test', setting('contact.email'));
        }
    }

    public function test_http_maps_form_fields_and_normalizes_only_its_checkboxes(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), [
            'general_site_name' => 'HTTP name',
        ])->assertRedirect(route('admin.settings.index'))->assertSessionHasNoErrors();

        $this->assertSame('HTTP name', setting('general.site_name'));
        $this->assertSame('kept@example.test', setting('contact.email'));
        $this->assertFalse(setting('front.nav_toggle_enabled'));
    }

    public function test_failed_patch_rolls_back_prior_groups_and_flushes_in_memory_values(): void
    {
        $fail = true;
        Setting::saving(static function (Setting $setting) use (&$fail): void {
            if ($fail && $setting->key === 'contact.email') {
                throw new RuntimeException('Simulated storage failure');
            }
        });

        try {
            app(SiteSettingsService::class)->update([
                'general.site_name' => 'Rolled back',
                'contact.email' => 'new@example.test',
            ]);
            $this->fail('The storage failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated storage failure', $exception->getMessage());
        } finally {
            $fail = false;
        }

        $this->assertSame('Before', Setting::getValue('general.site_name'));
        $this->assertSame('Before', setting('general.site_name'));
        $this->assertSame('kept@example.test', setting('contact.email'));
    }
}

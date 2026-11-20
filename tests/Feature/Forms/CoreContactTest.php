<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Forms\Services\ContactPageProvisioner;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\MenuItem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    Queue::fake();
    CmsFixtures::localization(['es', 'en']);
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', PrivacyFixtures::processingFor(['forms']), 'legal', 'json');
});

it('provides published localized contact and private intake without any installed module', function (): void {
    $page = app(ContactPageProvisioner::class)->provision();
    expect(CmsModule::query()->count())->toBe(0)
        ->and($page->is_published)->toBeTrue()
        ->and($page->template)->toBe('default')
        ->and($page->translations()->pluck('slug', 'locale')->all())->toEqual(['es' => 'contacto', 'en' => 'contact']);
    $form = CustomForm::query()->where('slug', 'contact')->sole();
    expect($form->notification_email)->toBeNull()
        ->and($form->fields()->pluck('field_key')->all())->toBe(['name', 'email', 'message'])
        ->and($form->actions)->toBe([['id' => 'confirmation', 'type' => 'confirm']]);
    $this->get('/es/contacto')->assertOk()->assertSee('data-cms-form="contact"', false)->assertSee('name="_hp_check"', false);
    $english = $this->get('/en/contact')->assertOk()->assertSee('Email address');
    $document = new DOMDocument;
    $document->loadHTML($english->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $action = (new DOMXPath($document))->evaluate('string((//form[@data-cms-form="contact"]/@action)[1])');
    expect($action)->toEndWith('/en/forms/contact/submit');
    $this->postJson($action, ['name' => 'Visitor', 'email' => 'visitor@example.test', 'message' => 'A private request'])
        ->assertOk()->assertJsonPath('success', true)->assertJsonPath('actions.0.type', 'confirm');
    $submission = FormSubmission::query()->sole();
    expect($submission->payload)->toBe(['name' => 'Visitor', 'email' => 'visitor@example.test', 'message' => 'A private request'])
        ->and($submission->ip_address)->toBeNull()->and($submission->user_agent)->toBeNull();
    expect($submission->locale)->toBe('en')
        ->and($submission->market_id)->toBe(Market::query()->where('code', 'es')->sole()->id);
    $this->postJson('/forms/contact/submit', ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Spam', '_hp_check' => 'filled'])
        ->assertUnprocessable();
    expect(FormSubmission::query()->count())->toBe(1);
});

it('submits the rendered contact action in a selected market and rejects a market used as a locale', function (): void {
    CmsFixtures::localization(['es', 'en'], ['us', 'gb']);
    app(ContactPageProvisioner::class)->provision();

    $english = $this->get('/us/en/contact')->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($english->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $action = (new DOMXPath($document))->evaluate('string((//form[@data-cms-form="contact"]/@action)[1])');
    expect($action)->toEndWith('/us/en/forms/contact/submit');
    $payload = ['name' => 'Visitor', 'email' => 'visitor@example.test', 'message' => 'A private request'];
    $this->postJson($action, $payload)->assertOk()->assertJsonPath('success', true);
    $submission = FormSubmission::query()->sole();
    expect($submission->locale)->toBe('en')
        ->and($submission->market_id)->toBe(Market::query()->where('code', 'us')->sole()->id);

    $this->postJson('/us/forms/contact/submit', $payload)->assertNotFound();
    $this->postJson('/zz/forms/contact/submit', $payload)->assertNotFound();
    expect(FormSubmission::query()->count())->toBe(1);
});

it('provisions contact idempotently and preserves editorial changes', function (): void {
    $provisioner = app(ContactPageProvisioner::class);
    $first = $provisioner->provision();
    $first->translations()->where('locale', 'es')->update(['title' => 'Habla con nuestro equipo']);
    CustomForm::query()->where('slug', 'contact')->sole()->fields()->where('field_key', 'name')->update(['is_required' => false]);
    $second = $provisioner->provision();
    expect($second->id)->toBe($first->id)
        ->and(Page::query()->count())->toBe(1)
        ->and(CustomForm::query()->count())->toBe(1)
        ->and($second->sections()->count())->toBe(1)
        ->and(MenuItem::query()->count())->toBe(1)
        ->and($second->translations()->where('locale', 'es')->sole()->title)->toBe('Habla con nuestro equipo')
        ->and(CustomForm::query()->sole()->fields()->where('field_key', 'name')->sole()->is_required)->toBeFalse();
});

it('reuses an explicitly owned demonstration contact page without replacing its composition', function (): void {
    $page = Page::query()->create(['template' => 'default', 'layout' => 'app', 'is_published' => true]);
    $page->translations()->create(['locale' => 'es', 'title' => 'Demo contact', 'slug' => 'contacto']);
    $section = $page->sections()->create(['type' => 'form', 'order' => 1, 'is_active' => true, 'status' => 'published']);
    $section->translations()->create(['locale' => 'es', 'content_json' => ['type' => 'form', 'form_slug' => 'contact', 'title' => 'Existing composition']]);
    app(GeneralSettingsServiceInterface::class)->set('forms.contact_page_id', $page->id, 'forms', 'number');

    $result = app(ContactPageProvisioner::class)->provision();

    expect($result->id)->toBe($page->id)
        ->and(Page::query()->count())->toBe(1)
        ->and($result->layout)->toBe('app')
        ->and($result->translations()->where('locale', 'es')->sole()->title)->toBe('Demo contact')
        ->and($result->sections()->count())->toBe(1)
        ->and($section->translations()->where('locale', 'es')->sole()->content_json['title'])->toBe('Existing composition')
        ->and(CustomForm::query()->where('slug', 'contact')->count())->toBe(1);
});

it('requires reviewed contact processing and rejects a conflicting page without partial provisioning', function (): void {
    $settings = app(GeneralSettingsServiceInterface::class);
    $settings->set('legal.processing', [], 'legal', 'json');
    expect(fn () => app(ContactPageProvisioner::class)->provision())->toThrow(ValidationException::class);
    expect(CustomForm::query()->count())->toBe(0)->and(Page::query()->count())->toBe(0);
    $settings->set('legal.processing', PrivacyFixtures::processingFor(['forms']), 'legal', 'json');
    $existing = Page::query()->create(['template' => 'default', 'is_published' => true]);
    $existing->translations()->create(['locale' => 'es', 'title' => 'Existing', 'slug' => 'contacto']);
    expect(fn () => app(ContactPageProvisioner::class)->provision())->toThrow(ValidationException::class);
    expect(CustomForm::query()->count())->toBe(0)->and(Page::query()->count())->toBe(1)
        ->and($settings->get('forms.contact_page_id'))->toBeNull();
});

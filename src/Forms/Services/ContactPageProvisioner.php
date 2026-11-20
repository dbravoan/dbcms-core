<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Menu;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Installs an editable contact page only after its processing has been reviewed. */
final class ContactPageProvisioner
{
    /** @var array<string, array{string,string,string,string,string,string,string}> */
    private const COPY = [
        'es' => ['Contacto', 'contacto', 'Nombre', 'Correo electrónico', 'Mensaje', 'Enviar mensaje', 'Gracias. Hemos recibido tu mensaje.'],
        'en' => ['Contact', 'contact', 'Name', 'Email address', 'Message', 'Send message', 'Thank you. Your message has been received.'],
        'ca' => ['Contacte', 'contacte', 'Nom', 'Correu electrònic', 'Missatge', 'Enviar missatge', 'Gràcies. Hem rebut el teu missatge.'],
        'fr' => ['Contact', 'contact', 'Nom', 'Adresse e-mail', 'Message', 'Envoyer le message', 'Merci. Votre message a été reçu.'],
        'it' => ['Contatti', 'contatti', 'Nome', 'Indirizzo email', 'Messaggio', 'Invia messaggio', 'Grazie. Il tuo messaggio è stato ricevuto.'],
        'pt' => ['Contacto', 'contacto', 'Nome', 'Endereço de email', 'Mensagem', 'Enviar mensagem', 'Obrigado. A sua mensagem foi recebida.'],
        'de' => ['Kontakt', 'kontakt', 'Name', 'E-Mail-Adresse', 'Nachricht', 'Nachricht senden', 'Vielen Dank. Ihre Nachricht wurde empfangen.'],
        'ar' => ['اتصل بنا', 'contact', 'الاسم', 'البريد الإلكتروني', 'الرسالة', 'إرسال الرسالة', 'شكرًا. تم استلام رسالتك.'],
        'zh-CN' => ['联系我们', 'contact', '姓名', '电子邮箱', '留言', '发送留言', '谢谢，我们已收到您的留言。'],
    ];

    public function __construct(
        private readonly GeneralSettingsServiceInterface $settings,
        private readonly LegalPageService $legal,
        private readonly BlockTree $blocks,
    ) {}

    public function provision(): Page
    {
        $this->legal->requireProcessing('forms');
        $locales = Language::query()->where('is_active', true)->pluck('code')->map(static fn (mixed $code): string => (string) $code)->all();
        if ($locales === [] || array_diff($locales, array_keys(self::COPY)) !== []) {
            throw new \DomainException('Prepare contact translations for every active locale before installation.');
        }

        try {
            return DB::transaction(function () use ($locales): Page {
                $page = Page::query()->lockForUpdate()->find((int) $this->settings->get('forms.contact_page_id', 0));
                if ($page === null) {
                    foreach ($locales as $locale) {
                        if (PageTranslation::query()->where('locale', $locale)->where('slug', self::COPY[$locale][1])->exists()) {
                            throw ValidationException::withMessages(['contact' => 'The contact page slug is already in use. Review the existing page before provisioning contact.']);
                        }
                    }
                }

                $form = CustomForm::query()->firstOrCreate(['slug' => 'contact'], [
                    'is_active' => true,
                    'notification_email' => null,
                    'actions' => [['id' => 'confirmation', 'type' => 'confirm']],
                ]);
                if ($form->wasRecentlyCreated) {
                    foreach (['name' => ['text', 'max:180'], 'email' => ['email', 'max:255'], 'message' => ['textarea', 'max:5000']] as $key => [$type, $rules]) {
                        $form->fields()->create(['field_key' => $key, 'type' => $type, 'is_required' => true, 'validation_rules' => $rules, 'order' => $form->fields()->count()]);
                    }
                }
                $fields = $form->fields()->get();
                foreach ($locales as $locale) {
                    [$title, , $name, $email, $message, $submit, $success] = self::COPY[$locale];
                    $form->translations()->firstOrCreate(['locale' => $locale, 'market_id' => null], ['name' => $title, 'submit_button_text' => $submit, 'success_message' => $success]);
                    $labels = ['name' => $name, 'email' => $email, 'message' => $message];
                    foreach ($fields as $field) {
                        if (isset($labels[$field->field_key])) {
                            $field->translations()->firstOrCreate(['locale' => $locale, 'market_id' => null], ['label' => $labels[$field->field_key]]);
                        }
                    }
                }

                $created = $page === null;
                $page ??= Page::query()->create(['template' => 'default', 'layout' => 'default', 'is_published' => true]);
                $section = $created ? $page->sections()->create([
                    'type' => 'form', 'order' => 1, 'is_active' => true, 'status' => 'published', 'published_at' => now(), 'styling_json' => ['columns_layout' => '1'],
                ]) : $page->sections()->where('type', 'form')->first();
                $payload = $this->blocks->identify(['type' => 'form', 'columns_layout' => '1', 'columns' => [['blocks' => [['type' => 'form', 'form_slug' => 'contact']]]]]);
                unset($payload['id']);
                foreach ($locales as $locale) {
                    [$title, $slug] = self::COPY[$locale];
                    $page->translations()->firstOrCreate(['locale' => $locale, 'market_id' => null], ['title' => $title, 'slug' => $slug, 'meta_title' => $title, 'schema_type' => 'ContactPage']);
                    $section?->translations()->firstOrCreate(['locale' => $locale, 'market_id' => null], ['content_json' => $payload]);
                }

                $menu = Menu::query()->where('location', 'header')->where('is_active', true)->first()
                    ?? Menu::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'location' => 'header', 'is_active' => true]);
                $item = $menu->items()->firstOrCreate(['type' => 'page', 'target_id' => $page->id], ['order' => (int) $menu->items()->max('order') + 1, 'is_active' => true, 'target_blank' => false]);
                foreach ($locales as $locale) {
                    $item->translations()->firstOrCreate(['locale' => $locale], ['title' => self::COPY[$locale][0]]);
                }
                $this->settings->set('forms.contact_page_id', $page->id, 'forms', 'number');

                return $page;
            });
        } finally {
            $this->settings->flushCache();
        }
    }
}

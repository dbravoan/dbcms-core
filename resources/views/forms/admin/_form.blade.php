@props([
    'form' => null,
    'fields' => [],
    'translationsMap' => [],
    'languages' => [],
    'markets' => [],
    'assignedMarketIds' => [],
])

@php
    $isEdit = ! empty($form && $form->id);
    $actionUrl = $isEdit ? route('admin.forms.update', $form) : route('admin.forms.store');

    $langs = $languages instanceof \Illuminate\Support\Collection ? $languages : collect($languages);
    if ($langs->isEmpty()) {
        $langs = collect([
            (object) ['code' => 'es', 'name' => 'Español'],
            (object) ['code' => 'en', 'name' => 'English'],
        ]);
    }
    $defaultLocale = $langs->first()->code ?? 'es';

    // Guarantee a complete per-locale map so the tabs never bind to undefined keys.
    $emptyLocale = ['name' => '', 'description' => '', 'submit_button_text' => 'Enviar mensaje', 'success_message' => '¡Gracias por contactarnos! Hemos recibido tu solicitud.'];
    $translationsByLocale = [];
    foreach ($langs as $lang) {
        $translationsByLocale[$lang->code] = array_merge($emptyLocale, $translationsMap[$lang->code] ?? []);
    }

    $assignedMarketIds = old('market_ids', $assignedMarketIds);
@endphp

<form method="POST" action="{{ $actionUrl }}" class="space-y-6 font-mono text-xs"
      x-data="{
        activeLocale: @js($defaultLocale),
        translations: @js($translationsByLocale),
        formSlug: @js(old('slug', $form?->slug ?? '')),
        slugTouched: @js($isEdit),
        ensure(code) {
            if (! this.translations[code]) {
                this.translations[code] = { name: '', description: '', submit_button_text: 'Enviar mensaje', success_message: '' };
            }
        },
        switchLocale(code) {
            this.ensure(code);
            this.activeLocale = code;
            window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } }));
        },
        generateSlug(text) {
            return (text || '').toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)+/g, '');
        }
      }"
      x-init="Object.keys(translations).forEach((code) => ensure(code)); ensure(activeLocale);"
      x-on:locale-tab-change="switchLocale($event.detail.locale)">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <!-- General Form Settings Card -->
    <x-admin.form-card class="rounded-xl space-y-4">
        <h3 class="text-sm font-bold text-[color:var(--admin-text)] border-b border-[color:var(--admin-border)] pb-3 flex items-center gap-2">
            <span class="text-[color:var(--admin-accent)]">1.</span> Configuración General del Formulario
        </h3>

        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[color:var(--admin-border)] pb-3">
            <x-admin.locale-tabs :languages="$langs" :flags="$flags ?? []" :active="$defaultLocale" group="form-editor" />
        </div>

        @foreach($langs as $lang)
            <div x-show="activeLocale === '{{ $lang->code }}'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">Nombre del Formulario ({{ strtoupper($lang->code) }}) *</label>
                    <input type="text"
                           name="translations[{{ $lang->code }}][name]"
                           x-model="translations['{{ $lang->code }}'].name"
                           @input="if (! slugTouched) formSlug = generateSlug(translations['{{ $lang->code }}'].name)"
                           placeholder="{{ __('admin.forms_ui.ph_form_name') }}"
                           class="admin-control w-full">
                </div>

                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">Descripción / Subtítulo ({{ strtoupper($lang->code) }})</label>
                    <input type="text"
                           name="translations[{{ $lang->code }}][description]"
                           x-model="translations['{{ $lang->code }}'].description"
                           placeholder="{{ __('admin.forms_ui.ph_description') }}"
                           class="admin-control w-full">
                </div>

                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">Texto del Botón de Envío ({{ strtoupper($lang->code) }}) *</label>
                    <input type="text"
                           name="translations[{{ $lang->code }}][submit_button_text]"
                           x-model="translations['{{ $lang->code }}'].submit_button_text"
                           class="admin-control w-full">
                </div>

                <div>
                    <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">Mensaje de Confirmación ({{ strtoupper($lang->code) }})</label>
                    <input type="text"
                           name="translations[{{ $lang->code }}][success_message]"
                           x-model="translations['{{ $lang->code }}'].success_message"
                           class="admin-control w-full">
                </div>
            </div>
        @endforeach

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.forms_ui.slug_label') }} *</label>
                <div class="flex items-center gap-2">
                    <input type="text"
                           name="slug"
                           x-model="formSlug"
                           @input="slugTouched = true"
                           placeholder="{{ __('admin.forms_ui.ph_form_slug') }}"
                           class="admin-control flex-1">
                    <button type="button" @click="formSlug = generateSlug(translations[activeLocale].name); slugTouched = false" class="px-2.5 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[10px]">{{ __('admin.forms_ui.sync') }}</button>
                </div>
                <p class="text-[10px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.forms_ui.slug_hint') }}<code class="text-[color:var(--admin-accent)]">&lt;x-forms::form-engine slug="..." /&gt;</code>).</p>
            </div>

            <div>
                <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.forms_ui.email_label') }}</label>
                <input type="email"
                       name="notification_email"
                       value="{{ old('notification_email', $form?->notification_email) }}"
                       placeholder="ventas@tuempresa.com"
                       class="admin-control w-full">
            </div>
        </div>

        <div>
            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.forms_ui.markets_label') }}</label>
            <x-admin.market-picker name="market_ids" :selected="$assignedMarketIds" :markets="$markets" group="form-editor" />
        </div>

        <div class="pt-2">
            <label class="flex items-center gap-2 cursor-pointer text-[color:var(--admin-text)] text-xs select-none">
                @if($isEdit)
                    <input type="hidden" name="is_active" value="0">
                @endif
                <input type="checkbox"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $form?->is_active ?? true) ? 'checked' : '' }}
                       class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                <span class="font-bold text-[color:var(--admin-text)]">{{ __('admin.forms_ui.active_label') }}</span>
            </label>
        </div>
    </x-admin.form-card>

    <!-- Dynamic Fields Builder -->
    <x-admin.form-card class="rounded-xl space-y-4">
        <h3 class="text-sm font-bold text-[color:var(--admin-text)] border-b border-[color:var(--admin-border)] pb-3 flex items-center gap-2">
            <span class="text-[color:var(--admin-accent)]">2.</span> Definición de Campos del Formulario
        </h3>

        @include('forms::admin._builder', ['fields' => old('fields', $fields ?? []), 'languages' => $langs])
    </x-admin.form-card>

    @include('forms::admin._composition')

    @if($isEdit)
        <div class="flex items-center justify-between pt-4 border-t border-[color:var(--admin-border)]">
            <button type="button"
                    @click="$dispatch('confirm-delete', {
                        action: '{{ route('admin.forms.destroy', $form) }}',
                        title: 'Eliminar Formulario',
                        message: '¿Estás seguro de eliminar este formulario y todos sus envíos?'
                    })"
                    class="px-4 py-2.5 bg-red-950/40 hover:bg-red-900/60 text-red-400 border border-red-800 rounded-lg transition-colors">
                Eliminar Formulario
            </button>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.forms.index') }}" class="px-5 py-2.5 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded-lg">{{ __('admin.actions.cancel') }}</a>
                <x-admin.button variant="primary" type="submit" class="px-6 py-2.5">{{ __('admin.actions.save') }}</x-admin.button>
            </div>
        </div>
    @else
        <x-admin.form-actions :submit="false" class="flex items-center justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
            <a href="{{ route('admin.forms.index') }}" class="px-5 py-2.5 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded-lg">{{ __('admin.actions.cancel') }}</a>
            <x-admin.button variant="primary" type="submit" class="px-6 py-2.5">{{ __('admin.forms_ui.save_form') }}</x-admin.button>
        </x-admin.form-actions>
    @endif
</form>

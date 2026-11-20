<form method="POST" action="{{ $blockComponent ? route('admin.block-components.update', $blockComponent) : route('admin.block-components.store') }}" class="space-y-6"
      data-cms-translation-editor x-data="{ activeLocale: @js($languages->firstWhere('is_default', true)?->code ?? $languages->first()?->code),
          locale(code) { this.activeLocale = code; window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } })); } }"
      x-on:locale-tab-change="locale($event.detail.locale)">
    @csrf
    @if($blockComponent) @method('PUT') @endif
    <input type="hidden" name="exposed_properties" value="{}">
    @if($errors->any())<x-admin.form-card><ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></x-admin.form-card>@endif
    @if($blockComponent?->getAttributes()['pending_revision_id'] ?? null)<p role="status">Estás editando una revisión pendiente; la versión publicada se conserva hasta su aprobación.</p>@endif
    <div class="grid xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <x-admin.form-card class="space-y-4">
                <h2 class="text-lg font-bold">Definición de la plantilla</h2>
                <x-admin.locale-tabs :languages="$languages" :active="$languages->firstWhere('is_default', true)?->code ?? $languages->first()?->code" group="component-form" />
                @foreach($languages as $language)
                    <div x-show="activeLocale === @js($language->code)">
                        <label for="component-title-{{ $language->code }}" class="admin-field-label">Nombre · {{ $language->name }}</label>
                        <input id="component-title-{{ $language->code }}" name="translations[{{ $language->code }}][title]" maxlength="255" class="admin-control w-full" value="{{ old('translations.'.$language->code.'.title', $blockComponent?->translations->whereNull('market_id')->firstWhere('locale', $language->code)?->title ?? '') }}">
                    </div>
                @endforeach
                <x-admin.block-manager entity-type="block_component" :entity-id="$blockComponent?->getKey()" :sections="old('sections_json', $sectionsData)" name="sections_json" :languages="$languages" :target="request('section')" />
            </x-admin.form-card>
        </div>
        <x-admin.form-card class="space-y-4 self-start">
            <h2 class="text-lg font-bold">Disponibilidad</h2>
            <input type="hidden" name="is_active" value="0"><label class="admin-check-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $blockComponent?->is_active ?? true))> Activada</label>
            <input type="hidden" name="is_published" value="0"><label class="admin-check-label"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $blockComponent?->is_published ?? true))> Publicada</label>
            <x-admin.market-picker name="market_ids" :selected="old('market_ids', $blockComponent?->markets->pluck('id')->all() ?? [])" :markets="$markets" group="component-form" />
            <p class="admin-field-hint">Las plantillas publicadas aparecen en el emergente del gestor de bloques. Sin mercados asignados se ofrece en todos los activos.</p>
            <x-admin.button variant="primary" type="submit">Guardar plantilla</x-admin.button>
            @if($blockComponent)<a class="block underline" href="{{ route('admin.block-components.preview', $blockComponent) }}" target="_blank" rel="noopener">Previsualizar</a>@endif
        </x-admin.form-card>
    </div>
</form>

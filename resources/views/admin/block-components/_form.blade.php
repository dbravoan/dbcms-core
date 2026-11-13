<form method="POST" action="{{ $blockComponent ? route('admin.block-components.update', $blockComponent) : route('admin.block-components.store') }}" class="space-y-6"
      data-cms-translation-editor x-data="{ activeLocale: @js($languages->firstWhere('is_default', true)?->code ?? $languages->first()?->code), properties: @js($componentProperties), targets: @js($propertyTargets),
          exposed() { const result = {}; for (const property of this.properties) { const target = this.targets[property.target] || property; result[property.name] = { node_id: target.node_id, field: target.field, label: property.label || property.name }; } return JSON.stringify(result); },
          validateProperties(event) { const names = new Set(); for (const input of this.$el.querySelectorAll('[data-component-property-name]')) { input.setCustomValidity(names.has(input.value) ? 'Usa un nombre distinto para cada propiedad.' : ''); names.add(input.value); } if (!this.$el.reportValidity()) event.preventDefault(); },
          locale(code) { this.activeLocale = code; window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } })); } }"
      x-on:locale-tab-change="locale($event.detail.locale)" x-on:submit="validateProperties($event)">
    @csrf
    @if($blockComponent) @method('PUT') @endif
    <input type="hidden" name="exposed_properties" :value="exposed()">
    @if($errors->any())<x-admin.form-card><ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></x-admin.form-card>@endif
    @if($blockComponent?->getAttributes()['pending_revision_id'] ?? null)<p role="status">Estás editando una revisión pendiente; la versión publicada se conserva hasta su aprobación.</p>@endif
    <div class="grid xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <x-admin.form-card class="space-y-4">
                <h2 class="text-lg font-bold">Definición del componente</h2>
                <x-admin.locale-tabs :languages="$languages" :active="$languages->firstWhere('is_default', true)?->code ?? $languages->first()?->code" group="component-form" />
                @foreach($languages as $language)
                    <div x-show="activeLocale === @js($language->code)">
                        <label for="component-title-{{ $language->code }}" class="admin-field-label">Nombre · {{ $language->name }}</label>
                        <input id="component-title-{{ $language->code }}" name="translations[{{ $language->code }}][title]" maxlength="255" class="admin-control w-full" value="{{ old('translations.'.$language->code.'.title', $blockComponent?->translations->whereNull('market_id')->firstWhere('locale', $language->code)?->title ?? '') }}">
                    </div>
                @endforeach
                <x-admin.block-manager entity-type="block_component" :entity-id="$blockComponent?->getKey()" :sections="old('sections_json', $sectionsData)" name="sections_json" :languages="$languages" :target="request('section')" />
            </x-admin.form-card>
            <x-admin.form-card class="space-y-4">
                <h2 class="text-lg font-bold">Propiedades expuestas</h2>
                <p class="text-sm">Cada instancia podrá modificar solamente estos campos de contenido. El resto de la composición conserva la definición compartida.</p>
                <p class="text-sm text-[color:var(--admin-muted)]">Guarda primero los bloques para disponer de destinos estables. Después elige un campo y dale un nombre. Si retiras un bloque, retira también sus propiedades expuestas.</p>
                <template x-for="(property, index) in properties" :key="index">
                    <fieldset class="grid md:grid-cols-2 gap-3 border rounded p-4">
                        <legend class="sr-only" x-text="'Propiedad ' + (index + 1)"></legend>
                        <label>Nombre para instancias<input data-component-property-name @input="$event.target.setCustomValidity('')" x-model="property.name" required pattern="[a-z][a-z0-9_]{0,63}" placeholder="titulo" class="admin-control w-full"></label>
                        <label>Etiqueta<input x-model="property.label" maxlength="255" placeholder="Título de la tarjeta" class="admin-control w-full"></label>
                        <label class="md:col-span-2">Campo de contenido<select x-model="property.target" required class="admin-control w-full"><option value="">Seleccionar campo…</option><template x-if="property.target &amp;&amp; !targets[property.target]"><option :value="property.target">Destino no disponible</option></template><template x-for="(target, key) in targets" :key="key"><option :value="key" x-text="target.label"></option></template></select></label>
                        <button type="button" @click="properties.splice(index, 1)" class="underline text-left">Retirar propiedad</button>
                    </fieldset>
                </template>
                <x-admin.button type="button" @click="properties.push({ name: '', label: '', target: '' })" x-bind:disabled="Object.keys(targets).length === 0">Exponer un campo</x-admin.button>
            </x-admin.form-card>
        </div>
        <x-admin.form-card class="space-y-4 self-start">
            <h2 class="text-lg font-bold">Disponibilidad</h2>
            <input type="hidden" name="is_active" value="0"><label class="flex gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $blockComponent?->is_active ?? false))> Activado</label>
            <input type="hidden" name="is_published" value="0"><label class="flex gap-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $blockComponent?->is_published ?? false))> Publicado</label>
            <x-admin.market-picker name="market_ids" :selected="old('market_ids', $blockComponent?->markets->pluck('id')->all() ?? [])" :markets="$markets" group="component-form" />
            <p class="text-sm">Solo se ofrecen referencias publicadas en el idioma y mercado de la página. Sin mercados asignados se aplica a todos los activos.</p>
            <x-admin.button variant="primary" type="submit">Guardar componente</x-admin.button>
            @if($blockComponent)<a class="block underline" href="{{ route('admin.block-components.preview', $blockComponent) }}" target="_blank" rel="noopener">Previsualizar versión editorial</a>@endif
        </x-admin.form-card>
    </div>
</form>

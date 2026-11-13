<section data-install-step="privacy" x-show="step === 3" class="space-y-4" aria-labelledby="install-privacy-heading">
    <h3 id="install-privacy-heading" tabindex="-1" class="text-lg font-bold text-white">Privacidad y alojamiento</h3>
    <p class="text-sm text-gray-400">Describe la configuración real del sitio. Esta información se utiliza para preparar las páginas de privacidad y cookies; no se publican datos de ejemplo.</p>
    <fieldset class="border border-gray-700 rounded-lg p-4">
        <legend class="px-1 text-sm font-bold">Dónde se aloja el sitio</legend>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach(['hosting_provider' => 'Proveedor de alojamiento', 'hosting_country' => 'País del alojamiento (código ISO, por ejemplo ES)'] as $key => $label)
                <label for="install-{{ $key }}" class="block text-sm">{{ $label }}
                    <input id="install-{{ $key }}" name="company[{{ $key }}]" required @if($key === 'hosting_country') minlength="2" maxlength="2" pattern="[A-Z]{2}" @else maxlength="255" @endif value="{{ old('company.'.$key) }}" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
                </label>
            @endforeach
        </div>
    </fieldset>
    <div x-data="{ rows: @js(old('company.processing', [['owner' => 'core']])) }" class="space-y-4">
        <h4 class="text-sm font-bold">Uso de datos personales</h4>
        <p class="text-xs text-gray-400">El tratamiento técnico del núcleo es necesario. Los demás dependen de las funciones que actives.</p>
        <template x-for="(row, index) in rows" :key="index">
            <fieldset class="border border-gray-700 rounded-lg p-4 space-y-3">
                <legend class="px-1 text-sm font-bold" x-text="row.owner || 'Nuevo tratamiento'"></legend>
                @include('install.processing-fields', ['dynamic' => true])
                <button type="button" x-show="rows.length > 1" @click="rows.splice(index, 1)" class="text-sm underline text-gray-300">Quitar tratamiento</button>
            </fieldset>
        </template>
        <button type="button" x-cloak @click="rows.push({owner: ''})" class="text-sm underline text-gray-300">Añadir tratamiento</button>
    </div>
    <noscript>
        <p class="text-sm text-gray-400">Sin JavaScript puedes completar los tratamientos mostrados. Para añadir otros, activa JavaScript o utiliza la instalación por CLI.</p>
        @foreach(old('company.processing', [['owner' => 'core']]) as $index => $row)
            <fieldset class="border border-gray-700 rounded-lg p-4 space-y-3">
                <legend class="px-1 text-sm font-bold">{{ $row['owner'] ?? 'Tratamiento' }}</legend>
                @include('install.processing-fields', ['dynamic' => false])
            </fieldset>
        @endforeach
    </noscript>
    <label class="flex gap-2 text-sm items-start"><input type="checkbox" name="company[reviewed]" value="1" required @checked(old('company.reviewed')) class="mt-1">He revisado que los datos del titular, el alojamiento y los tratamientos corresponden a esta instalación.</label>
</section>

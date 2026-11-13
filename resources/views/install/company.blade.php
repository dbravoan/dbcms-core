<section data-install-step="controller" x-show="step === 2" class="space-y-4" aria-labelledby="install-controller-heading"
         x-data="{ commercialActivity: @js((bool) old('company.commercial_activity', false)), registryRequired: @js((bool) old('company.registry_required', false)), dpoRequired: @js((bool) old('company.dpo_required', false)) }">
    <h3 id="install-controller-heading" tabindex="-1" class="text-lg font-bold text-white">Titular del sitio</h3>
    <p class="text-sm text-gray-400">Puede ser una persona, un profesional o una empresa. Estos datos aparecerán en las páginas legales del sitio.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <label for="install-company-name" class="block text-sm">Nombre completo o razón social
            <input id="install-company-name" name="company[company_name]" required maxlength="180" value="{{ old('company.company_name') }}" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
        </label>
        <label for="install-tax-id" class="block text-sm">NIF / CIF
            <input id="install-tax-id" name="company[tax_id]" :required="commercialActivity" maxlength="50" value="{{ old('company.tax_id') }}" aria-describedby="install-tax-help" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
            <span id="install-tax-help" class="block text-xs text-gray-400 mt-1">Obligatorio si realizas actividad económica. No necesitas aportar datos registrales si no te corresponden.</span>
        </label>
    </div>
    <label class="flex gap-2 text-sm items-start"><input type="hidden" name="company[commercial_activity]" value="0"><input type="checkbox" name="company[commercial_activity]" value="1" x-model="commercialActivity" @checked(old('company.commercial_activity')) class="mt-1">Realizo actividad económica con este sitio.</label>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach(['activity' => ['Descripción del sitio o actividad', 'text', 255], 'address' => ['Dirección del titular', 'text', 255], 'country' => ['País del titular (código ISO, por ejemplo ES)', 'text', 2], 'contact_email' => ['Correo de contacto', 'email', 255], 'privacy_email' => ['Correo para ejercer derechos', 'email', 255]] as $key => [$label, $type, $max])
            <label for="install-{{ $key }}" class="block text-sm">{{ $label }}
                <input id="install-{{ $key }}" type="{{ $type }}" name="company[{{ $key }}]" required maxlength="{{ $max }}" @if($key === 'country') minlength="2" pattern="[A-Z]{2}" @endif value="{{ old('company.'.$key) }}" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
            </label>
        @endforeach
    </div>
    <p class="text-xs text-gray-400">Puedes utilizar el mismo correo para contacto y para ejercer derechos.</p>
    <details class="border border-gray-700 rounded-lg p-4 space-y-3" @if(old('company.registry_required') || old('company.dpo_required') || old('company.commercial_registry') || old('company.dpo_contact')) open @endif>
        <summary class="cursor-pointer text-sm font-bold">Información adicional, si corresponde</summary>
        <label class="flex gap-2 text-sm items-start"><input type="hidden" name="company[registry_required]" value="0"><input type="checkbox" name="company[registry_required]" value="1" x-model="registryRequired" @checked(old('company.registry_required')) class="mt-1">Debo indicar una inscripción registral.</label>
        <label for="install-registry" x-show="registryRequired" class="block text-sm">Datos de inscripción registral
            <input id="install-registry" name="company[commercial_registry]" :required="registryRequired" maxlength="500" value="{{ old('company.commercial_registry') }}" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
        </label>
        <label class="flex gap-2 text-sm items-start"><input type="hidden" name="company[dpo_required]" value="0"><input type="checkbox" name="company[dpo_required]" value="1" x-model="dpoRequired" @checked(old('company.dpo_required')) class="mt-1">Debo designar un delegado de protección de datos.</label>
        <label for="install-dpo" x-show="dpoRequired" class="block text-sm">Contacto del delegado de protección de datos
            <input id="install-dpo" name="company[dpo_contact]" :required="dpoRequired" maxlength="500" value="{{ old('company.dpo_contact') }}" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
        </label>
    </details>
</section>

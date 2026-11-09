@extends('admin.layout')

@section('title', 'Privacidad y tratamientos')

@section('content')
<div class="admin-page space-y-6 max-w-5xl mx-auto pb-16">
    <x-admin.form-header title="Privacidad y tratamientos" description="Revisa los datos del responsable y los tratamientos que se publican en las páginas legales." :back-route="route('admin.settings.index')" back-label="Volver a ajustes" />

    <form action="{{ route('admin.settings.privacy.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="revision" value="{{ old('revision', $revision) }}">
        <x-admin.form-card class="space-y-4">
            <h2 class="text-lg font-semibold">Responsable y empresa</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach(['company_name' => 'Nombre o razón social', 'address' => 'Domicilio completo', 'country' => 'País (código ISO)', 'contact_email' => 'Correo de contacto', 'privacy_email' => 'Correo para ejercer derechos', 'activity' => 'Actividad', 'hosting_provider' => 'Proveedor de alojamiento', 'hosting_country' => 'País del alojamiento (código ISO)', 'tax_id' => 'Identificación fiscal', 'commercial_registry' => 'Inscripción registral', 'dpo_contact' => 'Contacto del delegado de protección de datos'] as $key => $label)
                    <label class="block text-sm">{{ $label }}
                        <input class="admin-control block w-full mt-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]" type="text" name="company[{{ $key }}]" value="{{ old('company.'.$key, $company[$key] ?? '') }}" @required(in_array($key, ['company_name', 'address', 'country', 'contact_email', 'privacy_email', 'activity', 'hosting_provider', 'hosting_country']))>
                    </label>
                @endforeach
            </div>
            @foreach(['commercial_activity' => 'Realizo actividad económica', 'registry_required' => 'La inscripción registral es obligatoria', 'dpo_required' => 'Debo designar un delegado de protección de datos'] as $key => $label)
                <label class="flex gap-2 text-sm"><input type="hidden" name="company[{{ $key }}]" value="0"><input type="checkbox" name="company[{{ $key }}]" value="1" @checked(old('company.'.$key, $company[$key] ?? false))>{{ $label }}</label>
            @endforeach
        </x-admin.form-card>

        <x-admin.form-card class="space-y-4">
            <div class="space-y-4" x-data="{ rows: @js(old('company.processing', $company['processing'])) }">
            <h2 class="text-lg font-semibold">Tratamientos</h2>
            <p class="text-sm">Describe los datos reales, sus destinatarios y su conservación. No elimines tratamientos que sigan utilizándose.</p>
            <template x-for="(row, index) in rows" :key="index">
                <fieldset class="border border-[color:var(--admin-border)] rounded-lg p-4 space-y-3">
                    <legend class="font-semibold" x-text="row.owner || 'Nuevo tratamiento'"></legend>
                    <label class="block text-sm">Identificador del tratamiento
                        <input class="admin-control block w-full mt-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]" :name="`company[processing][${index}][owner]`" x-model="row.owner" required>
                    </label>
                    @foreach(['purpose' => 'Finalidad', 'legal_basis' => 'Base jurídica', 'data' => 'Datos tratados', 'retention' => 'Plazo o criterio de conservación', 'recipients' => 'Destinatarios y proveedores', 'transfers' => 'Transferencias internacionales y garantías, o ausencia de ellas'] as $key => $label)
                        <label class="block text-sm">{{ $label }}
                            <textarea class="admin-control block w-full mt-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]" :name="`company[processing][${index}][{{ $key }}]`" x-model="row.{{ $key }}" required rows="2"></textarea>
                        </label>
                    @endforeach
                    <label class="block text-sm">Conservación automática en días
                        <input class="admin-control block w-full mt-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]" type="number" min="1" max="36500" :name="`company[processing][${index}][retention_days]`" x-model="row.retention_days">
                    </label>
                    <label class="flex gap-2 text-sm"><input type="hidden" :name="`company[processing][${index}][manual_review]`" value="0"><input type="checkbox" :name="`company[processing][${index}][manual_review]`" value="1" x-model="row.manual_review">La conservación admite una revisión manual documentada.</label>
                    <button type="button" class="text-sm underline" @click="rows.splice(index, 1)">Quitar tratamiento</button>
                </fieldset>
            </template>
            <x-admin.button type="button" @click="rows.push({owner: '', manual_review: false})">Añadir tratamiento</x-admin.button>
            </div>
        </x-admin.form-card>

        <x-admin.form-card class="space-y-4">
            <label class="flex gap-2 text-sm"><input type="checkbox" name="company[reviewed]" value="1" required>He revisado que estos datos y tratamientos corresponden a esta instalación.</label>
            <x-admin.button variant="primary" type="submit">Guardar privacidad</x-admin.button>
        </x-admin.form-card>
    </form>
</div>
@endsection

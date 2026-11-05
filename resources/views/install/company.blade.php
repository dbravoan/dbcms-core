<fieldset class="p-5 border border-gray-700 rounded space-y-4">
    <legend class="font-bold">Responsable y privacidad</legend>
    <p class="text-sm">Estos datos se publicarán en el aviso legal y la política de privacidad. Describe los tratamientos reales, sus destinatarios y su conservación.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach(['company_name' => 'Nombre o razón social', 'address' => 'Domicilio completo', 'country' => 'País (ISO, por ejemplo ES)', 'contact_email' => 'Email de contacto', 'privacy_email' => 'Email para ejercer derechos', 'activity' => 'Actividad', 'hosting_provider' => 'Proveedor de alojamiento', 'hosting_country' => 'País del alojamiento'] as $key => $label)
            <label class="block text-sm">{{ $label }}<input name="company[{{ $key }}]" required value="{{ old('company.'.$key) }}" class="block w-full bg-gray-900 border border-gray-600 rounded p-2"></label>
        @endforeach
        @foreach(['tax_id' => 'Identificación fiscal (obligatoria para actividad económica)', 'commercial_registry' => 'Inscripción registral, si corresponde', 'dpo_contact' => 'Delegado de protección de datos, si corresponde'] as $key => $label)
            <label class="block text-sm">{{ $label }}<input name="company[{{ $key }}]" value="{{ old('company.'.$key) }}" class="block w-full bg-gray-900 border border-gray-600 rounded p-2"></label>
        @endforeach
    </div>
    @foreach(['commercial_activity' => 'Realizo actividad económica (requiere identificación fiscal)', 'registry_required' => 'La inscripción registral es obligatoria (rellenar los datos)', 'dpo_required' => 'Debo designar un delegado de protección de datos (rellenar contacto)'] as $key => $label)
        <label class="flex gap-2 text-sm"><input type="hidden" name="company[{{ $key }}]" value="0"><input type="checkbox" name="company[{{ $key }}]" value="1" @checked(old('company.'.$key))>{{ $label }}</label>
    @endforeach
    <div x-data="{ rows: @js(old('company.processing', [['owner' => 'core']])) }" class="space-y-5">
        <template x-for="(row, index) in rows" :key="index">
            <fieldset class="border border-gray-700 p-4 space-y-2">
                <label class="block text-sm">Tratamiento (core (obligatorio), accounts, blog_comments, forms, shop_inquiries, shop_orders u otro identificador)
                    <input :name="`company[processing][${index}][owner]`" x-model="row.owner" required class="block w-full bg-gray-900 rounded p-2">
                </label>
                @foreach(['purpose' => 'Finalidad', 'legal_basis' => 'Base jurídica', 'data' => 'Datos tratados', 'retention' => 'Plazo o criterio de conservación', 'recipients' => 'Destinatarios y proveedores', 'transfers' => 'Transferencias internacionales y garantías, o ausencia de ellas'] as $key => $label)
                    <label class="block text-sm">{{ $label }}<textarea :name="`company[processing][${index}][{{ $key }}]`" x-model="row.{{ $key }}" required rows="2" class="block w-full bg-gray-900 rounded p-2"></textarea></label>
                @endforeach
                <label class="block text-sm">Conservación automática en días (obligatorio para core, blog_comments, forms y shop_inquiries; desde la recepción, o la última actividad para pedidos)
                    <input type="number" min="1" max="36500" :name="`company[processing][${index}][retention_days]`" x-model="row.retention_days" class="block w-full bg-gray-900 rounded p-2">
                </label>
                <label class="flex gap-2 text-sm"><input type="checkbox" :name="`company[processing][${index}][manual_review]`" value="1" x-model="row.manual_review">Este tratamiento admite revisión documentada del criterio de conservación antes de suprimir datos (obligatoria para accounts; no indiques días automáticos para cuentas).</label>
                <button type="button" x-show="rows.length > 1" @click="rows.splice(index, 1)">Quitar tratamiento</button>
            </fieldset>
        </template>
        <button type="button" @click="rows.push({owner: ''})" class="underline">Añadir tratamiento</button>
    </div>
    <label class="flex gap-2 text-sm"><input type="checkbox" name="company[reviewed]" value="1" required>He revisado que estos datos y tratamientos corresponden a esta instalación.</label>
</fieldset>

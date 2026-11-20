<div class="cms-inspector-advanced">
    <section class="cms-inspector-essentials">
        <h4 class="cms-inspector-essentials-title">Reutilización</h4>
        <p class="admin-field-hint">Copia el bloque seleccionado para reutilizarlo o depurarlo.</p>
        <div class="cms-inspector-actions">
            <x-admin.button type="button" variant="ghost" size="sm" @click="copySelected('json')">Datos JSON</x-admin.button>
            <x-admin.button type="button" variant="ghost" size="sm" @click="copySelected('blade')">Copiar Blade</x-admin.button>
            <span role="status" class="admin-field-hint" x-text="copyStatus"></span>
        </div>
        <textarea x-show="dataExport" :value="dataExport" readonly rows="5" aria-label="Código del bloque seleccionado" class="admin-control w-full" @focus="$event.target.select()"></textarea>
    </section>

    <section class="cms-inspector-essentials">
        <label class="admin-field">
            <span class="admin-field-label">Clases del proyecto</span>
            <input type="text" class="admin-control" :value="appearanceClasses()" @change="setAppearanceClasses($event.target.value)" placeholder="Heredar">
            <span class="admin-field-hint" x-text="'Disponibles: ' + (Array.isArray(editorOptions.appearanceClasses) ? editorOptions.appearanceClasses : Object.keys(editorOptions.appearanceClasses || {})).join(', ')"></span>
        </label>
    </section>

    <details class="cms-inspector-section">
        <summary class="cms-inspector-summary">
            <span class="cms-inspector-summary-label">Valores guardados</span>
            <i class="fa-solid fa-chevron-down cms-inspector-summary-chevron" aria-hidden="true"></i>
        </summary>
        <p class="admin-field-hint">Consulta los valores propios, incluidos ajustes personalizados. Este listado no los modifica.</p>
        <textarea readonly rows="6" class="admin-control w-full" :value="savedPresentation()" aria-label="Valores de presentación guardados"></textarea>
    </details>

    <details x-show="bindingFields().length" class="cms-inspector-section" x-cloak>
        <summary class="cms-inspector-summary">
            <span class="cms-inspector-summary-label">Datos públicos dinámicos</span>
            <i class="fa-solid fa-chevron-down cms-inspector-summary-chevron" aria-hidden="true"></i>
        </summary>
        <template x-for="field in bindingFields()" :key="field.key">
            <fieldset class="cms-inspector-field">
                <legend class="admin-field-label" x-text="field.label"></legend>
                <select class="admin-control" :value="bindingFor(field.key).source || ''" @change="setBindingSource(field.key, $event.target.value)" :aria-label="'Fuente de ' + field.label">
                    <template x-for="(label, source) in bindingSourceOptions()" :key="source">
                        <option :value="source" x-text="label"></option>
                    </template>
                </select>
                <template x-if="bindingFor(field.key).source">
                    <div class="cms-inspector-field">
                        <select class="admin-control" :value="bindingFor(field.key).field || ''" @change="setBindingValue(field.key, 'field', $event.target.value)" :aria-label="'Campo público para ' + field.label">
                            <option value="">Selecciona un campo</option>
                            <template x-for="(descriptor, name) in bindingSourceFields(field.key)" :key="name">
                                <option :value="name" x-text="descriptor.label || name"></option>
                            </template>
                        </select>
                        <input x-show="bindingFor(field.key).source !== 'item'" type="number" min="1" step="1" class="admin-control" :value="bindingFor(field.key).id || ''" @change="setBindingValue(field.key, 'id', $event.target.value)" aria-label="ID público de la referencia" placeholder="ID de la referencia pública">
                    </div>
                </template>
            </fieldset>
        </template>
    </details>
</div>

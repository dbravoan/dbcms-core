@php
    $builderLanguages = $languages instanceof \Illuminate\Support\Collection
        ? $languages->pluck('code')->values()->all()
        : (is_array($languages) ? array_values($languages) : []);

    if ($builderLanguages === []) {
        $builderLanguages = ['es'];
    }

    // Available field types come from the core registry, so a disabled module
    // (e.g. Captcha) never offers its field type in the builder.
    $fieldTypes = $fieldTypes ?? app(\DbCms\Core\Forms\Support\FieldTypeOptions::class)->list();
@endphp

<div x-data="formFieldsBuilder({{ json_encode($fields ?? []) }}, {{ json_encode($builderLanguages) }}, 'es', {{ json_encode(array_values($fieldTypes)) }})" class="space-y-6">

    <!-- Field Manager Toolbar & Quick Presets -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl">
        <div class="space-y-1">
            <h4 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <span class="text-[color:var(--admin-accent)]">&gt;</span> Campos Dinámicos del Formulario
            </h4>
            <p class="text-xs text-[color:var(--admin-muted)]">
                Añade, reordena y configura los campos. Las etiquetas y opciones se editan por idioma (<span class="text-[color:var(--admin-accent)] font-bold" x-text="activeLocale.toUpperCase()"></span>).
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <template x-for="fieldType in fieldTypes" :key="fieldType.key">
                <button type="button"
                        @click="addField(fieldType.key)"
                        :disabled="!canAdd(fieldType.key)"
                        :class="!canAdd(fieldType.key) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-[color:var(--admin-surface-raised)] hover:text-[color:var(--admin-text)]'"
                        class="px-2.5 py-1.5 bg-[color:var(--admin-surface)] text-[color:var(--admin-text)] rounded border border-[color:var(--admin-border)] text-xs flex items-center gap-1.5 transition-colors">
                    <span x-text="fieldType.add"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- Empty State -->
    <template x-if="items.length === 0">
        <div class="p-8 text-center bg-[color:var(--admin-surface)] border border-dashed border-[color:var(--admin-border)] rounded-xl text-[color:var(--admin-muted)] font-mono">
            &gt; No hay campos configurados. Pulsa los botones superiores para añadir campos al formulario.
        </div>
    </template>

    <!-- Field Items List -->
    <div class="space-y-3">
        <template x-for="(item, index) in items" :key="item._uid">
            <div class="p-4 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)]/40 rounded-xl transition-all space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]/80">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center text-xs font-bold text-[color:var(--admin-accent)]" x-text="index + 1"></span>
                        <span class="font-bold text-[color:var(--admin-text)] text-sm" x-text="labelFor(item, index)"></span>
                        <span class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] border border-[color:var(--admin-border)]" x-text="item.type"></span>
                        <span x-show="item.is_required" class="px-2 py-0.5 rounded text-[10px] font-semibold bg-red-950/50 text-red-400 border border-red-800/50">{{ __('admin.forms_ui.mandatory') }}</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(index)" :disabled="index === 0" class="p-1 text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] disabled:opacity-30">
                            <i class="fa-solid fa-arrow-up text-xs"></i>
                        </button>
                        <button type="button" @click="moveDown(index)" :disabled="index === items.length - 1" class="p-1 text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] disabled:opacity-30">
                            <i class="fa-solid fa-arrow-down text-xs"></i>
                        </button>
                        <button type="button" @click="removeItem(index)" class="p-1 ml-2 text-red-400 hover:text-red-300">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <!-- Structural configuration (locale independent) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] font-bold mb-1 uppercase tracking-wider text-[10px]">{{ __('admin.forms_ui.builder_key') }} *</label>
                        <input type="text"
                               :name="'fields[' + index + '][field_key]'"
                               x-model="item.field_key"
                               :readonly="item.type === 'captcha'"
                               required
                               placeholder="{{ __('admin.forms_ui.ph_key') }}"
                               class="admin-control w-full">
                    </div>

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] font-bold mb-1 uppercase tracking-wider text-[10px]">{{ __('admin.forms_ui.builder_type') }} *</label>
                        <select :name="'fields[' + index + '][type]'"
                                x-model="item.type"
                                class="admin-control w-full">
                            <template x-for="fieldType in fieldTypes" :key="fieldType.key">
                                <option :value="fieldType.key" x-text="fieldType.label"></option>
                            </template>
                            <template x-if="!fieldTypes.some((t) => t.key === item.type)">
                                <option :value="item.type" x-text="'⚠ ' + item.type"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="item.type !== 'captcha'">
                        <label class="admin-field-label block text-[color:var(--admin-muted)] font-bold mb-1 uppercase tracking-wider text-[10px]">{{ __('admin.forms_ui.builder_rules') }}</label>
                        <input type="text"
                               :name="'fields[' + index + '][validation_rules]'"
                               x-model="item.validation_rules"
                               placeholder="{{ __('admin.forms_ui.ph_rules') }}"
                               class="admin-control w-full">
                    </div>

                    <div class="flex items-center gap-3 pt-5">
                        <label x-show="item.type !== 'captcha'" class="flex items-center gap-2 cursor-pointer text-[color:var(--admin-text)] text-xs select-none">
                            <input type="hidden" :name="'fields[' + index + '][is_required]'" value="0">
                            <input type="checkbox"
                                   :name="'fields[' + index + '][is_required]'"
                                   value="1"
                                   x-model="item.is_required"
                                   class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                            <span class="font-bold text-[color:var(--admin-text)]">{{ __('admin.forms_ui.builder_required') }}</span>
                        </label>
                        <span x-show="item.type === 'captcha'" class="text-[10px] uppercase tracking-wider text-[color:var(--admin-accent)] font-bold pt-1">
                            {{ __('admin.forms_ui.captcha_always_required') }}
                        </span>
                    </div>
                </div>

                <!-- Localized label / placeholder / options -->
                @foreach($languages as $lang)
                    <div x-show="activeLocale === '{{ $lang->code }}'" class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 border-t border-[color:var(--admin-border)]/60">
                        <div>
                            <label class="admin-field-label block text-[color:var(--admin-muted)] font-bold mb-1 uppercase tracking-wider text-[10px]">
                                Etiqueta / Label ({{ strtoupper($lang->code) }}) *
                            </label>
                            <input type="text"
                                   :name="'fields[' + index + '][translations][{{ $lang->code }}][label]'"
                                   x-model="item.translations['{{ $lang->code }}'].label"
                                   @input="if (! item.field_key || item.field_key.startsWith('campo_')) item.field_key = slugify(item.translations['{{ $lang->code }}'].label)"
                                   placeholder="{{ __('admin.forms_ui.ph_label') }}"
                                   class="admin-control w-full">
                        </div>

                        <div x-show="item.type !== 'captcha'">
                            <label class="admin-field-label block text-[color:var(--admin-muted)] font-bold mb-1 uppercase tracking-wider text-[10px]">
                                Placeholder / Pista ({{ strtoupper($lang->code) }})
                            </label>
                            <input type="text"
                                   :name="'fields[' + index + '][translations][{{ $lang->code }}][placeholder]'"
                                   x-model="item.translations['{{ $lang->code }}'].placeholder"
                                   placeholder="{{ __('admin.forms_ui.ph_placeholder') }}"
                                   class="admin-control w-full">
                        </div>

                        <div x-show="item.type === 'select' || item.type === 'radio'" class="col-span-full">
                            <label class="admin-field-label block text-[color:var(--admin-muted)] font-bold mb-1 uppercase tracking-wider text-[10px]">
                                Opciones ({{ strtoupper($lang->code) }}) — {{ __('admin.forms_ui.options_hint') }} <span class="text-[color:var(--admin-accent)]">{{ __('admin.forms_ui.value_label') }}</span>
                            </label>
                            <textarea :name="'fields[' + index + '][translations][{{ $lang->code }}][options]'"
                                      x-model="item.translations['{{ $lang->code }}'].options"
                                      rows="3"
                                      placeholder="{!! __('admin.forms_ui.options_placeholder') !!}"
                                      class="admin-control w-full"></textarea>
                        </div>
                    </div>
                @endforeach
            </div>
        </template>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function formFieldsBuilder(initialFields, initialLanguages, initialLocale, initialFieldTypes) {
    return {
        items: [],
        languages: (Array.isArray(initialLanguages) && initialLanguages.length) ? initialLanguages : ['es'],
        activeLocale: initialLocale || 'es',
        fieldTypes: (Array.isArray(initialFieldTypes) ? initialFieldTypes : []),
        uidSeq: 0,

        init() {
            this.items = (initialFields || []).map((item) => this.ensureField(item));
            this.$watch('items', () => window.dispatchEvent(new CustomEvent('cms-form-fields-changed', {detail:this.items.map(item => item.field_key)})));

            window.addEventListener('cms-locale-switched', (event) => {
                const code = event.detail && event.detail.locale;
                if (code && this.languages.includes(code)) {
                    this.activeLocale = code;
                }
            });
        },

        ensureField(item) {
            if (typeof item !== 'object' || item === null) {
                item = {};
            }
            item._uid = item._uid || ('f' + (++this.uidSeq));
            item.field_key = item.field_key || '';
            item.type = item.type || 'text';
            item.is_required = !! item.is_required;
            item.validation_rules = item.validation_rules || '';
            if (! item.translations) {
                item.translations = {};
            }
            for (const code of this.languages) {
                if (! item.translations[code]) {
                    item.translations[code] = { label: '', placeholder: '', options: '' };
                } else {
                    item.translations[code].options = item.translations[code].options || '';
                }
            }

            // A captcha field is a single, always-required verification widget.
            if (item.type === 'captcha') {
                item.field_key = 'captcha';
                item.is_required = true;
                item.validation_rules = '';
            }

            return item;
        },

        hasFieldOfType(type) {
            return this.items.some((item) => item.type === type);
        },

        canAdd(type) {
            // Only one captcha per form.
            return type !== 'captcha' || ! this.hasFieldOfType('captcha');
        },

        labelFor(item, index) {
            const translation = item.translations && item.translations[this.activeLocale];
            return (translation && translation.label) ? translation.label : ('Campo #' + (index + 1));
        },

        slugify(text) {
            return (text || '')
                .toString()
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .trim()
                .replace(/\s+/g, '_')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-+/g, '_');
        },

        addField(type) {
            if (! this.canAdd(type)) {
                return;
            }

            const defaults = {
                text: { label: 'Nombre o Texto', placeholder: 'Escribe aquí...', validation: 'nullable|string|max:255' },
                email: { label: 'Correo Electrónico', placeholder: 'ejemplo@dominio.com', validation: 'nullable|email' },
                tel: { label: 'Teléfono de Contacto', placeholder: '+34 600 000 000', validation: 'nullable|string|max:20' },
                textarea: { label: 'Mensaje o Consulta', placeholder: 'Detalla tu consulta...', validation: 'nullable|string' },
                select: { label: 'Selecciona una Opción', placeholder: '', validation: 'nullable|string', options: "opcion_1 | Opción 1\nopcion_2 | Opción 2\nopcion_3 | Opción 3" },
                checkbox: { label: 'Acepto la política de privacidad y tratamiento de datos', placeholder: '', validation: 'accepted' },
                number: { label: 'Cantidad o Presupuesto', placeholder: '1000', validation: 'nullable|numeric' },
                radio: { label: 'Tipo de Servicio', placeholder: '', validation: 'nullable|string', options: "basico | Básico\nprofesional | Profesional\nenterprise | Enterprise" },
                captcha: { label: 'Verificación de seguridad', placeholder: '', validation: '' }
            };

            const isCaptcha = type === 'captcha';
            const typeOption = this.fieldTypes.find((fieldType) => fieldType.key === type);
            const config = defaults[type] || {
                label: typeOption ? typeOption.label : type,
                placeholder: '',
                validation: ''
            };

            const translations = {};
            for (const code of this.languages) {
                translations[code] = {
                    label: config.label,
                    placeholder: config.placeholder,
                    options: config.options || ''
                };
            }

            this.items.push(this.ensureField({
                field_key: isCaptcha ? 'captcha' : (this.slugify(config.label) + '_' + (this.items.length + 1)),
                type: type,
                is_required: isCaptcha ? true : (type === 'checkbox'),
                validation_rules: isCaptcha ? '' : config.validation,
                translations: translations
            }));
        },

        removeItem(index) {
            this.items.splice(index, 1);
        },

        moveUp(index) {
            if (index > 0) {
                const temp = this.items[index];
                this.items[index] = this.items[index - 1];
                this.items[index - 1] = temp;
            }
        },

        moveDown(index) {
            if (index < this.items.length - 1) {
                const temp = this.items[index];
                this.items[index] = this.items[index + 1];
                this.items[index + 1] = temp;
            }
        }
    };
}
</script>

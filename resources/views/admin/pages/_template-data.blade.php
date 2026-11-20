@php
    $localizedRows = $page?->translations->whereNull('market_id')->keyBy('locale');
@endphp

@foreach($templates as $template => $fields)
    @continue($fields === [])
    <fieldset x-show="selectedTemplate === @js($template)"
              x-bind:disabled="selectedTemplate !== @js($template)"
              @disabled($selectedTemplate !== $template)
              x-cloak>
        <x-admin.form-card class="space-y-4">
            <h3 class="font-bold text-[color:var(--admin-text)]">{{ __('admin.entity_form.template_data') }}</h3>
            @foreach($allLangs as $lang)
                @php
                    $stored = $page?->template === $template ? ($localizedRows?->get($lang->code)?->template_data ?? []) : [];
                    $active = 'selectedTemplate === '.\Illuminate\Support\Js::from($template).' && activeLocale === '.\Illuminate\Support\Js::from($lang->code);
                @endphp
                <div x-show="activeLocale === @js($lang->code)" class="space-y-4">
                    <p>{{ $lang->name }} ({{ strtoupper($lang->code) }}) · {{ __('admin.entity_form.template_fields_inherit_help') }}</p>
                    @foreach($fields as $key => $field)
                        @php
                            $name = 'translations['.$lang->code.'][template_data]['.$key.']';
                            $value = $stored[$key] ?? null;
                            if ($selectedTemplate === $template) {
                                $value = old('translations.'.$lang->code.'.template_data.'.$key, $value);
                            }
                        @endphp
                        @if($field['type'] === 'rich_text')
                            @php
                                $richText = app(\DbCms\Core\Services\Content\RichTextContent::class);
                                $value = $richText->source($value, $field['max_length']);
                                if ($value !== null && $value['format'] === 'html') {
                                    $value['content'] = $richText->render($value);
                                }
                            @endphp
                            <x-admin.rich-text-editor :label="$field['label']"
                                                  :name="$name"
                                                  :value="$value"
                                                  :when="$active"
                                                  :data-blade-locale="$lang->code" :data-blade-field="$key" />
                        @else
                            <x-admin.input :label="$field['label']" :name="$name" :value="is_string($value) ? $value : ''"
                                           :id="'blade-'.$template.'-'.$lang->code.'-'.$key" :maxlength="$field['max_length']"
                                           :data-blade-locale="$lang->code" :data-blade-field="$key" />
                        @endif
                    @endforeach
                </div>
            @endforeach
        </x-admin.form-card>
    </fieldset>
@endforeach

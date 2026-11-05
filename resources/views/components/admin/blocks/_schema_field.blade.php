@props([
    'model' => 'block',
    'keyExpr' => 'field.key',
    'fieldVar' => 'field',
    'blockKey' => 'block',
    'idExpr' => null,
    'isChild' => false,
])

@php
    $idExpression = $idExpr ?: "'block-field-' + ".$blockKey.".id + '-' + ".$keyExpr;
    $hintExpression = $idExpression." + '-hint'";
@endphp

<div class="admin-field" x-show="fieldVisible({{ $model }}, {{ $fieldVar }})" :class="['textarea', 'json', 'array', 'list', 'repeater', 'media'].includes({{ $fieldVar }}.type) ? 'md:col-span-2' : ''">
    <label x-show="{{ $fieldVar }}.type !== 'repeater'" :for="{!! $idExpression !!}" class="admin-field-label" x-text="{{ $fieldVar }}.label || {{ $keyExpr }}"></label>
    <span x-show="{{ $fieldVar }}.type === 'repeater'" :id="{!! $idExpression !!} + '-label'" class="admin-field-label" x-text="{{ $fieldVar }}.label || {{ $keyExpr }}"></span>

    <template x-if="['text', 'string', 'url', 'email'].includes({{ $fieldVar }}.type || 'text')">
        <input :id="{!! $idExpression !!}"
               :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
               :type="{{ $fieldVar }}.type === 'email' ? 'email' : 'text'"
               :inputmode="{{ $fieldVar }}.type === 'url' ? 'url' : null"
               :required="{{ $fieldVar }}.required === true"
               :placeholder="{{ $fieldVar }}.placeholder || null"
               x-model="{{ $model }}[{{ $keyExpr }}]"
               @input="syncBlocksToLocale()"
               class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm">
    </template>

    <template x-if="{{ $fieldVar }}.type === 'textarea'">
        <textarea :id="{!! $idExpression !!}"
                  :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
                  :required="{{ $fieldVar }}.required === true"
                  :placeholder="{{ $fieldVar }}.placeholder || null"
                  x-model="{{ $model }}[{{ $keyExpr }}]"
                  @input="syncBlocksToLocale()"
                  rows="5"
                  class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm"></textarea>
    </template>

    <template x-if="['number', 'integer'].includes({{ $fieldVar }}.type)">
        <input :id="{!! $idExpression !!}"
               :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
               type="number"
               :step="{{ $fieldVar }}.step ?? ({{ $fieldVar }}.type === 'integer' ? 1 : 'any')"
               :min="{{ $fieldVar }}.min ?? null"
               :max="{{ $fieldVar }}.max ?? null"
               :required="{{ $fieldVar }}.required === true"
               x-model.number="{{ $model }}[{{ $keyExpr }}]"
               @input="syncBlocksToLocale()"
               class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm">
    </template>

    <template x-if="{{ $fieldVar }}.type === 'boolean'">
        <input :id="{!! $idExpression !!}"
               :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
               type="checkbox"
               x-model="{{ $model }}[{{ $keyExpr }}]"
               @change="syncBlocksToLocale()"
               class="rounded border border-[color:var(--admin-border)]">
    </template>

    <template x-if="{{ $fieldVar }}.type === 'select'">
        <select :id="{!! $idExpression !!}"
                :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
                :required="{{ $fieldVar }}.required === true"
                x-model="{{ $model }}[{{ $keyExpr }}]"
                @change="syncBlocksToLocale()"
                class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm">
            <template x-for="(label, key) in ({{ $fieldVar }}.options || {})" :key="key">
                <option :value="Array.isArray({{ $fieldVar }}.options) ? label : key" x-text="label"></option>
            </template>
        </select>
    </template>

    <template x-if="['json', 'array'].includes({{ $fieldVar }}.type)">
        <textarea :id="{!! $idExpression !!}"
                  :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
                  :value="JSON.stringify({{ $model }}[{{ $keyExpr }}] || [], null, 2)"
                  @change="updateJsonValue({{ $model }}, {{ $keyExpr }}, $event.target)"
                  rows="7"
                  spellcheck="false"
                  class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm font-mono"></textarea>
    </template>

    <template x-if="{{ $fieldVar }}.type === 'list'">
        <textarea :id="{!! $idExpression !!}"
                  :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
                  :value="({{ $model }}[{{ $keyExpr }}] || []).join('\n')"
                  @change="{{ $model }}[{{ $keyExpr }}] = $event.target.value.split('\n').filter(value => value.trim()); syncBlocksToLocale()"
                  rows="4"
                  class="admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm"
                  placeholder="Un elemento por línea"></textarea>
    </template>

    <template x-if="{{ $fieldVar }}.type === 'media'">
        <div class="space-y-2">
            <div class="flex items-start gap-3">
                <div class="w-16 h-16 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-center overflow-hidden shrink-0">
                    <template x-if="{{ $model }}[{{ $keyExpr }}] && {{ $fieldVar }}.media_kind !== 'video'">
                        <img :src="{{ $model }}[{{ $keyExpr }}]" alt="" class="w-full h-full object-contain">
                    </template>
                    <template x-if="{{ $model }}[{{ $keyExpr }}] && {{ $fieldVar }}.media_kind === 'video'">
                        <i class="fa-solid fa-video text-[color:var(--admin-accent)] text-lg"></i>
                    </template>
                    <template x-if="!{{ $model }}[{{ $keyExpr }}]">
                        <i class="fa-solid fa-image text-[color:var(--admin-muted)] text-lg"></i>
                    </template>
                </div>

                <div class="flex-1 min-w-0 space-y-2">
                    <div class="flex gap-2">
                        <input :id="{!! $idExpression !!}"
                               :aria-describedby="{{ $fieldVar }}.description ? {!! $hintExpression !!} : null"
                               type="text"
                               x-model="{{ $model }}[{{ $keyExpr }}]"
                               @input="syncBlocksToLocale()"
                               placeholder="Selecciona un archivo o pega una URL"
                               class="admin-control flex-1 min-w-0 rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm font-mono">
                        <button type="button"
                                @click="openBlockMedia({{ $model }}, {{ $keyExpr }}, {{ $fieldVar }})"
                                class="px-3 py-2 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] rounded-lg font-bold"
                                :title="{{ $fieldVar }}.media_kind === 'video' ? 'Seleccionar vídeo' : 'Seleccionar imagen'">
                            <i class="fa-solid" :class="{{ $fieldVar }}.media_kind === 'video' ? 'fa-film' : 'fa-image'"></i>
                        </button>
                        <button type="button"
                                @click="clearBlockMedia({{ $model }}, {{ $keyExpr }})"
                                class="px-3 py-2 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] rounded-lg font-bold"
                                title="Quitar medio">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <p class="text-[10px] text-[color:var(--admin-muted)]" x-show="{{ $fieldVar }}.media_kind === 'video'">Selecciona un vídeo local o pega una URL directa. YouTube se configura en el campo específico del bloque.</p>
                </div>
            </div>
        </div>
    </template>

    <span :id="{!! $hintExpression !!}"
          x-show="{{ $fieldVar }}.description"
          x-text="{{ $fieldVar }}.description"
          class="admin-field-hint"></span>
</div>

@php
    $form = $form ?? [];
    $isEdit = ($form['mode'] ?? 'create') === 'edit';
    $value = static fn (string $key): string => (string) old($key, $form[$key] ?? '');
    $checked = (bool) old('is_indexable', $form['is_indexable'] ?? false);
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div>
        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.filters.col_module') }} *</label>
        <select name="module" required @disabled($isEdit) class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] disabled:opacity-60">
            @foreach($filterModules as $key => $label)
                <option value="{{ $key }}" @selected($value('module') === $key)>{{ $label }} [{{ $key }}]</option>
            @endforeach
        </select>
        @if($isEdit)
            <input type="hidden" name="module" value="{{ $value('module') }}">
        @endif
    </div>

    <div>
        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.filters.col_market') }}</label>
        <select name="market_code" @disabled($isEdit) class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] disabled:opacity-60">
            <option value="">{{ __('admin.filters.all_markets') }}</option>
            @foreach($markets as $market)
                <option value="{{ $market->code }}" @selected($value('market_code') === $market->code)>{{ strtoupper($market->code) }} — {{ $market->name }}</option>
            @endforeach
        </select>
        @if($isEdit)
            <input type="hidden" name="market_code" value="{{ $value('market_code') }}">
        @endif
    </div>

    <div>
        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.filters.col_locale') }} *</label>
        <select name="locale" required @disabled($isEdit) class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] disabled:opacity-60">
            @foreach($languages as $language)
                <option value="{{ $language->code }}" @selected(($value('locale') ?: app()->getLocale()) === $language->code)>{{ strtoupper($language->code) }} — {{ $language->name }}</option>
            @endforeach
        </select>
        @if($isEdit)
            <input type="hidden" name="locale" value="{{ $value('locale') }}">
        @endif
    </div>

    <div class="md:col-span-3">
        <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.filters.col_path') }} *</label>
        <input type="text" name="path" required value="{{ $value('path') }}" maxlength="191"
               @readonly($isEdit)
               placeholder="{{ __('admin.filters.path_placeholder') }}"
               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] font-mono read-only:opacity-60">
        <p class="text-[10px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.filters.path_hint') }}</p>
    </div>

    <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.filters.field_title') }}</label>
            <input type="text" name="title" value="{{ $value('title') }}" maxlength="255" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
        </div>
        <div>
            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.filters.field_heading') }}</label>
            <input type="text" name="heading" value="{{ $value('heading') }}" maxlength="255" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
        </div>
        <div class="md:col-span-2">
            <label class="admin-field-label block font-bold text-[color:var(--admin-muted)] uppercase mb-1">{{ __('admin.filters.field_description') }}</label>
            <textarea name="meta_description" rows="2" maxlength="500" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">{{ $value('meta_description') }}</textarea>
        </div>
    </div>

    <div class="flex items-center gap-2 md:col-span-3">
        <input type="hidden" name="is_indexable" value="0">
        <input type="checkbox" name="is_indexable" value="1" @checked($checked) class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
        <span class="text-[color:var(--admin-text)]">{{ __('admin.filters.col_indexable') }}</span>
        <span class="text-[10px] text-[color:var(--admin-muted)]">{{ __('admin.filters.indexable_hint') }}</span>
    </div>
</div>

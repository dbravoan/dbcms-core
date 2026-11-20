@php($b = $blockKey)
<div class="space-y-3">
    <label class="admin-field">
        <span class="admin-field-label">{{ __('admin.block_form.section_title_optional') }}</span>
        <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" class="admin-control">
    </label>
    <x-admin.rich-text-editor :label="__('admin.rich_text.content')"
                              :source="'({format: '.$b.'.format ?? \'markdown\', content: '.$b.'.content ?? \'\'})'"
                              :on-update="'Object.assign('.$b.', $event.detail); syncBlocksToLocale()'" />
</div>

@php
    $typography = \DbCms\Core\Services\Content\SafeContentRenderer::typography();
    $origins = app(\DbCms\Core\Services\Privacy\ExternalContentPolicy::class)->assetOrigins();
    $menus = [
        'heading' => ['heading', ['paragraph' => __('admin.rich_text.paragraph')] + array_combine(
            range(1, 6), array_map(fn ($level) => __('admin.rich_text.heading').' '.$level, range(1, 6)),
        )],
        'list' => ['list-ul', [
            'none' => __('admin.rich_text.no_list'),
            'bulletList' => __('admin.rich_text.bullet_list'),
            'orderedList' => __('admin.rich_text.ordered_list'),
        ]],
        'text-align' => ['align-left', array_replace($typography['text-align'], ['' => __('admin.rich_text.default_alignment')])],
    ];
@endphp
<template id="cms-rich-text-interface" data-render-url="{{ route('admin.rich-text.render') }}"
          data-typography="{{ json_encode($typography) }}" data-origins="{{ json_encode($origins) }}">
    <div class="admin-rich-toolbar flex flex-wrap items-center gap-1 border-b border-[color:var(--admin-border)] p-2">
        @foreach(['visual', 'markdown', 'html'] as $mode)
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mode="{{ $mode }}" aria-pressed="false">{{ __('admin.rich_text.'.$mode) }}</button>
        @endforeach
        <button type="button" class="admin-btn admin-btn--icon admin-btn--sm" data-toggle-preview aria-pressed="false"
                title="{{ __('admin.rich_text.preview') }}" aria-label="{{ __('admin.rich_text.preview') }}">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
        @if(auth()->user()?->isAdmin() || auth()->user()?->hasPermission('manage:media'))
            <button type="button" class="admin-btn admin-btn--icon admin-btn--sm" data-media
                    title="{{ __('admin.rich_text.media') }}" aria-label="{{ __('admin.rich_text.media') }}">
                <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
            </button>
        @endif
    </div>
    <div data-visual-tools class="admin-rich-toolbar flex flex-wrap items-center gap-1 border-b border-[color:var(--admin-border)] p-2" role="group" aria-label="{{ __('admin.rich_text.formatting') }}">
        <div class="cms-rich-toolbar-group">
            @foreach(['undo' => 'arrow-rotate-left', 'redo' => 'arrow-rotate-right'] as $command => $icon)
                <button type="button" class="admin-btn admin-btn--icon admin-btn--sm" data-command="{{ $command }}" data-rich-history
                        title="{{ __('admin.block_editor_js.'.$command) }}" aria-label="{{ __('admin.block_editor_js.'.$command) }}">
                    <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>
                </button>
            @endforeach
        </div>
        <div class="cms-rich-toolbar-group">
            @foreach($menus as $key => [$icon, $options])
                <details class="cms-rich-menu" data-choice="{{ $key }}">
                    <summary class="admin-btn admin-btn--icon admin-btn--sm" title="{{ __('admin.rich_text.'.$key) }}">
                        <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>
                        <span class="sr-only">{{ __('admin.rich_text.'.$key) }}</span>
                        <i class="fa-solid fa-chevron-down text-[9px]" aria-hidden="true"></i>
                    </summary>
                    <div class="cms-rich-menu-panel">
                        @foreach($options as $value => $label)
                            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-option="{{ $value }}" aria-pressed="false">{{ $label }}</button>
                        @endforeach
                    </div>
                </details>
            @endforeach
            <details class="cms-rich-menu" data-typography-menu>
                <summary class="admin-btn admin-btn--icon admin-btn--sm" title="{{ __('admin.rich_text.typography') }}">
                    <i class="fa-solid fa-font" aria-hidden="true"></i>
                    <span class="sr-only">{{ __('admin.rich_text.typography') }}</span>
                    <i class="fa-solid fa-chevron-down text-[9px]" aria-hidden="true"></i>
                </summary>
                <div class="cms-rich-menu-panel space-y-1">
                    @foreach(['font-family', 'font-weight'] as $property)
                        <select class="admin-control" data-style="{{ $property }}" aria-label="{{ __('admin.rich_text.'.$property) }}">
                            <option value="">{{ __('admin.rich_text.'.$property) }}</option>
                            @foreach(array_diff_key($typography[$property], ['' => true]) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endforeach
                </div>
            </details>
        </div>
        <div class="cms-rich-toolbar-group">
            @foreach([
                ['toggleBold', 'bold', 'bold', 'bold'],
                ['toggleItalic', 'italic', 'italic', 'italic'],
                ['toggleStrike', 'strike', 'strikethrough', 'strike'],
                ['toggleBlockquote', 'quote', 'quote-left', 'blockquote'],
                ['toggleCode', 'code', 'code', 'code'],
            ] as [$command, $key, $icon, $active])
                <button type="button" class="admin-btn admin-btn--icon admin-btn--sm" data-command="{{ $command }}"
                        data-active-mark="{{ $active }}" aria-pressed="false"
                        title="{{ __('admin.rich_text.'.$key) }}" aria-label="{{ __('admin.rich_text.'.$key) }}">
                    <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>
                </button>
            @endforeach
            <button type="button" class="admin-btn admin-btn--icon admin-btn--sm" data-link data-active-mark="link"
                    aria-pressed="false" title="{{ __('admin.rich_text.link') }}" aria-label="{{ __('admin.rich_text.link') }}">
                <i class="fa-solid fa-link" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div data-editor-columns>
        <div data-canvas class="min-h-40 p-3"></div>
        <textarea data-source hidden class="admin-control min-h-40 w-full border-0 font-mono" aria-label="{{ __('admin.rich_text.source') }}" spellcheck="false"></textarea>
        <div data-preview hidden class="cms-rich-text border-t border-[color:var(--admin-border)] p-3" role="region" aria-label="{{ __('admin.rich_text.preview') }}"></div>
    </div>
    <p data-status role="status" class="text-sm text-[color:var(--admin-muted)]"></p>
</template>

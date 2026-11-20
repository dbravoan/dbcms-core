@php
    /**
     * Backoffice access button for the front navigation.
     *
     * Hidden unless `front.nav_admin_button` is enabled in the backoffice.
     */
    $nav = $frontNav ?? [];
    $show = (bool) ($nav['admin'] ?? false);
    $url = $nav['adminUrl'] ?? url('/'.config('cms.admin_path', 'admin'));
    $label = $label ?? ($nav['adminLabel'] ?? 'Admin');
@endphp

@if($show)
    <a href="{{ $url }}" data-front-nav class="{{ $class ?? '' }}">{!! $label !!}</a>
@endif

<div data-cms-consent="{{ $category }}">
    @isset($service)<p>{{ $service['provider'] }} — {{ $service['purpose'] }}</p>@endisset
    <template>{!! $html !!}</template>
    <p data-consent-placeholder role="status">
        {{ __('privacy.external_blocked') }}
        <button type="button" data-dispatch="open-cookie-settings">{{ __('privacy.configure') }}</button>
    </p>
</div>

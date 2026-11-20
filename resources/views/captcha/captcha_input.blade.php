@if(captcha_available())
    @php
        $ui = \DbCms\Core\Forms\FormUi::classes($variant ?? 'cyber');
        $service = app(\DbCms\Core\Captcha\Services\CaptchaService::class);
        $challenge = $service->challenge();
        $captchaLabel = isset($field) && $field instanceof \DbCms\Core\Forms\FieldSchema
            ? $field->label()
            : __('Verificación de Seguridad Captcha');
        $nonce = $cspNonce ?? '';
        $uid = substr(md5(uniqid('captcha', true)), 0, 8);
        $errorKeys = ['captcha_response', 'g-recaptcha-response', 'cf-turnstile-response'];
    @endphp

    <div class="{{ trim(($wrapperClass ?? '').' space-y-1') }}">
        <label class="{{ $ui['label'] }}">
            {{ $captchaLabel }}
            <span class="{{ $ui['star'] }}">*</span>
        </label>

        {{-- Internal math challenge (SVG + signed token) --}}
        @if($challenge['internal'] !== null)
            <div class="flex items-center space-x-3">
                <div class="shrink-0">
                    {!! $challenge['internal']['svg'] !!}
                </div>
                <input type="hidden" name="_captcha_token" value="{{ $challenge['internal']['token'] }}">
                <input type="text"
                       name="captcha_response"
                       @required($challenge['provider'] === null)
                       placeholder="{{ __('Resultado...') }}"
                       autocomplete="off"
                       class="{{ $ui['input'] }}">
            </div>
        @endif

        @if($challenge['provider'] !== null)
        <div data-cms-consent="external">
            <p data-consent-placeholder>{{ __('legal.captcha_fallback') }} <button type="button" data-dispatch="open-cookie-settings">{{ __('legal.settings') }}</button></p>
            <template>
        {{-- Google reCAPTCHA v3 (invisible): the token is fetched on submit --}}
        @if($challenge['provider'] === 'recaptcha')
            <input type="hidden" name="g-recaptcha-response" id="cms-captcha-{{ $uid }}">
            <script src="https://www.google.com/recaptcha/api.js?render={{ $challenge['site_key'] }}" nonce="{{ $nonce }}" async defer></script>
            <script nonce="{{ $nonce }}">
                (function () {
                    var field = document.getElementById('cms-captcha-{{ $uid }}');
                    var script = document.currentScript;
                    var form = script ? script.closest('form') : null;

                    if (! field || ! form) {
                        return;
                    }

                    form.addEventListener('submit', function (event) {
                        if (field.value || form.elements.namedItem('captcha_response')?.value || ! window.grecaptcha) {
                            return;
                        }

                        event.preventDefault();
                        grecaptcha.ready(function () {
                            grecaptcha.execute('{{ $challenge['site_key'] }}', { action: 'submit' }).then(function (token) {
                                field.value = token;
                                form.submit();
                            });
                        });
                    });
                })();
            </script>
        @endif

        {{-- Cloudflare Turnstile (managed widget) --}}
        @if($challenge['provider'] === 'turnstile')
            <div class="cf-turnstile"
                 data-sitekey="{{ $challenge['site_key'] }}"
                 data-response-field-name="cf-turnstile-response"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" nonce="{{ $nonce }}" async defer></script>
        @endif

            </template>
        </div>
        @endif

        @foreach($errorKeys as $errorKey)
            @if(isset($errors) && $errors->has($errorKey))
                <p class="{{ $ui['error'] }}">{{ $errors->first($errorKey) }}</p>
                @break
            @endif
        @endforeach
    </div>
@endif

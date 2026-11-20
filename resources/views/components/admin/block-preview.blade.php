<!doctype html>
<html lang="{{ $previewLocale }}" data-cms-preview class="front-{{ $previewTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="Content-Security-Policy" content="form-action 'none'">
    <title>Previsualización de bloque</title>
    @vite(cms_asset_entries(true))
    <style nonce="{{ $cspNonce ?? '' }}">
        [data-cms-preview-selected] { outline: 2px solid var(--cms-accent, #2563eb); outline-offset: 3px; }
        .cms-block-preview { margin: 0; min-height: 100vh; }
    </style>
</head>
<body class="cms-block-preview" data-preview-root="{{ $previewRootId ?? '' }}" data-preview-node="{{ $selectedNodeId ?? '' }}" @if(isset($selectedColumnIndex)) data-preview-column="{{ $selectedColumnIndex }}" @endif>
    <x-cms::content-region :theme="$previewTheme">
        {!! $html !!}
    </x-cms::content-region>
</body>
</html>

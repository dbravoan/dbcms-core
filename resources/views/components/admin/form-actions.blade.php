@props(['cancelRoute' => null, 'submitLabel' => 'Guardar', 'submitDisabled' => false, 'submit' => true])

<div {{ $attributes->class(['admin-form-actions']) }}>
    @if($cancelRoute)<x-admin.button :href="$cancelRoute" variant="neutral">Cancelar</x-admin.button>@endif
    {{ $slot }}
    @if($submit)<x-admin.button type="submit" variant="primary" :disabled="$submitDisabled">{{ $submitLabel }}</x-admin.button>@endif
</div>

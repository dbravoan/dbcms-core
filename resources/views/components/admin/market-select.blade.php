@props([
    'name' => null,
    'field' => null,
    'value' => null,
    'values' => [],
    'multiple' => false,
    'valueKey' => 'code',
    'placeholder' => 'Seleccionar mercado...',
    'perPage' => 20,
    'help' => null,
])

{{-- Domain wrapper: market selector backed by the generic AJAX search-select. --}}
<x-admin.search-select
    source="markets"
    :name="$name"
    :field="$field"
    :value="$value"
    :values="$values"
    :multiple="$multiple"
    :value-key="$valueKey"
    :placeholder="$placeholder"
    :per-page="$perPage"
    :help="$help"
    {{ $attributes }} />

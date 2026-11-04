<h2>{{ __('legal.controller') }}</h2>
<dl>
    @foreach(['company_name' => 'controller', 'tax_id' => 'tax_id', 'address' => 'address', 'country' => 'address', 'activity' => 'activity', 'commercial_registry' => 'registry', 'dpo_contact' => 'dpo'] as $field => $label)
        @if(!empty($company[$field]))<dt class="font-semibold">{{ __('legal.'.$label) }}</dt><dd>{{ $company[$field] }}</dd>@endif
    @endforeach
    <dt class="font-semibold">{{ __('legal.contact') }}</dt><dd><a href="mailto:{{ $company['contact_email'] }}">{{ $company['contact_email'] }}</a></dd>
</dl>

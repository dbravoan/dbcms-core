@props(['owner', 'allowManualReview' => false])
@php
    $legal = app(\DbCms\Core\Services\Privacy\LegalPageService::class);
    $processing = $legal->processing($owner);
@endphp
<aside class="text-xs cms-text-muted space-y-1">
    @if($processing && $legal->canCollect($owner, $allowManualReview))
        <p><strong>{{ __('legal.controller') }}:</strong> {{ setting('legal.company_name') }}.</p>
        @foreach(['purpose' => 'purpose', 'legal_basis' => 'basis', 'retention' => 'retention', 'recipients' => 'recipients'] as $key => $label)
            <p><strong>{{ __('legal.'.$label) }}:</strong> {{ $processing[$key] }}</p>
        @endforeach
        <p>{{ __('legal.rights_short') }} <a class="underline" href="{{ $legal->privacyUrl() }}">{{ __('legal.privacy') }}</a></p>
    @else
        <p role="status">{{ __('legal.collection_unavailable') }}</p>
    @endif
</aside>

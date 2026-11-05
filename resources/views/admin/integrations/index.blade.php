@extends('admin.layout')

@section('title', __('integrations.title'))

@section('content')
<div class="admin-page space-y-6">
    <x-admin.form-header :title="__('integrations.title')" :description="__('integrations.description')" :back-route="route('admin.modules.index')" />
    <p class="text-sm text-[color:var(--admin-muted)]">{{ __('integrations.available_inactive') }}</p>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($guides as $guide)
            <article class="admin-card p-6 flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-admin.chip variant="brand">{{ $guide->platform }}</x-admin.chip>
                    <x-admin.chip>v{{ $guide->version }}</x-admin.chip>
                </div>
                <h2 class="text-lg font-bold text-[color:var(--admin-text)]">{{ $guide->title }}</h2>
                <p class="text-sm text-[color:var(--admin-muted)]">{{ __('integrations.steps', ['count' => count($guide->steps)]) }} · {{ __('integrations.reviewed') }} <time datetime="{{ $guide->reviewedAt }}">{{ $guide->reviewedAt }}</time></p>
                <div class="mt-auto">
                    <x-admin.button :href="route('admin.modules.guides.show', ['identifier' => $module, 'guide' => $guide->id])" variant="primary" :aria-label="__('integrations.open_named', ['title' => $guide->title])">{{ __('integrations.open') }}</x-admin.button>
                </div>
            </article>
        @endforeach
    </div>
</div>
@endsection

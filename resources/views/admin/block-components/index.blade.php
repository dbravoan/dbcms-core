@extends('admin.layout')
@section('title', __('admin.nav.items.block_templates'))
@section('content')
<div class="admin-page space-y-6">
    <x-admin.datatable-header :title="__('admin.nav.items.block_templates')" :description="__('admin.block_templates.description')" :search-placeholder="__('admin.block_templates.search')" :create-route="route('admin.block-components.create')" :create-label="__('admin.nav.create.block_template')">
        <x-admin.select name="is_active" :label="__('admin.common.availability')" :value="request('is_active', 'all')" :options="['all' => __('admin.listing.all_statuses'), '1' => __('admin.status.active'), '0' => __('admin.status.inactive')]" />
        <x-admin.select name="is_published" :label="__('admin.common.status')" :value="request('is_published', 'all')" :options="['all' => __('admin.listing.all_statuses'), '1' => __('admin.status.published'), '0' => __('admin.status.draft')]" />
    </x-admin.datatable-header>
    <x-admin.table :empty="$components->isEmpty()" :emptyTitle="__('admin.block_templates.empty')" :emptyDescription="__('admin.block_templates.empty_description')" :emptyActionRoute="route('admin.block-components.create')" :emptyActionLabel="__('admin.nav.create.block_template')">
        <x-slot:header>
            <th scope="col" class="px-4 py-3">{{ __('admin.common.name') }}</th>
            <x-admin.sortable-th column="is_published" :label="__('admin.common.status')" />
            <th scope="col" class="px-4 py-3 text-right">{{ __('admin.common.actions') }}</th>
        </x-slot:header>
        @foreach($components as $blockComponent)
            <tr>
                <td class="px-4 py-3 font-bold">{{ $blockComponent->getAttribute('admin_title') }}</td>
                <td class="px-4 py-3"><x-admin.status-badge :status="$blockComponent->getAttribute('admin_status')" /></td>
                <td class="px-4 py-3 text-right">
                    <div class="inline-flex items-center gap-1.5">
                        <x-admin.action-button :href="route('admin.block-components.edit', $blockComponent)" icon="fa-solid fa-pen-to-square" :tooltip="__('admin.actions.edit')" variant="primary" />
                        <x-admin.action-button :href="route('admin.block-components.preview', $blockComponent)" target="_blank" rel="noopener" icon="fa-solid fa-arrow-up-right-from-square" :tooltip="__('admin.actions.preview')" variant="view" />
                        <x-admin.action-button icon="fa-solid fa-trash-can" :tooltip="__('admin.actions.delete')" variant="danger" @click="$dispatch('confirm-delete', {{ Illuminate\Support\Js::from(['action' => route('admin.block-components.destroy', $blockComponent), 'title' => __('admin.block_templates.delete_title'), 'message' => __('admin.block_templates.delete_message')]) }})" />
                    </div>
                </td>
            </tr>
        @endforeach
        <x-slot:pagination><x-admin.datatable-pagination :paginator="$components" /></x-slot:pagination>
    </x-admin.table>
</div>
@endsection

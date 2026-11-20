@extends('admin.layout')

@section('title', __('admin.filemanager.library_title'))

@section('content')
<div class="admin-page">
    <x-admin.form-header :title="__('admin.filemanager.library_title')" :description="__('admin.filemanager.library_description')" />
    <x-admin.media-manager />
</div>
@endsection

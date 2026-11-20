@extends('admin.layout')
@section('title', __('admin.nav.create.block_template'))
@section('content')
<div class="admin-page space-y-6"><x-admin.form-header :title="__('admin.nav.create.block_template')" :backRoute="route('admin.block-components.index')" backLabel="Volver a plantillas" />@include('admin.block-components._form')</div>
@endsection

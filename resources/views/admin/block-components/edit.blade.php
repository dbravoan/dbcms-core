@extends('admin.layout')
@section('title', 'Editar plantilla')
@section('content')
<div class="admin-page space-y-6"><x-admin.form-header title="Editar plantilla" :backRoute="route('admin.block-components.index')" backLabel="Volver a plantillas" />@include('admin.block-components._form')</div>
@endsection

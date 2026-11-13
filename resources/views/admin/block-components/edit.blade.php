@extends('admin.layout')
@section('title', 'Editar componente reutilizable')
@section('content')
<div class="admin-page space-y-6"><x-admin.form-header title="Editar componente reutilizable" :backRoute="route('admin.block-components.index')" backLabel="Volver a componentes" />@include('admin.block-components._form')</div>
@endsection

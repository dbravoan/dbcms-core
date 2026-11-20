@extends('errors.layout')

@section('title', 'Error Interno del Servidor')
@section('code', '500')
@section('icon')
    <i class="fa-solid fa-triangle-exclamation"></i>
@endsection
@section('message')
    Se ha producido una anomalía inesperada durante la ejecución del proceso en el servidor. El incidente ha quedado registrado en los registros de auditoría.
@endsection

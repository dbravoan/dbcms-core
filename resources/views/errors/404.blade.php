@extends('errors.layout')

@section('title', 'Página no encontrada')
@section('code', '404')
@section('icon')
    <i class="fa-solid fa-compass"></i>
@endsection
@section('message')
    El recurso o página solicitada no existe, ha sido movida o ya no se encuentra disponible en el sistema.
@endsection

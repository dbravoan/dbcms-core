@extends('errors.layout')

@section('title', 'Sesión o Token Caducado')
@section('code', '419')
@section('icon')
    <i class="fa-solid fa-clock-rotate-left"></i>
@endsection
@section('message')
    La sesión de navegación o el token de seguridad CSRF ha expirado por inactividad. Por favor, refresca la página para continuar.
@endsection

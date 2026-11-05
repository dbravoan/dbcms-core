@extends('errors.layout')

@section('title', 'Acceso Restringido')
@section('code', '403')
@section('icon')
    <i class="fa-solid fa-shield-halved"></i>
@endsection
@section('message')
    No dispones de las autorizaciones, permisos de rol o alcance OAuth2 necesarios para acceder al recurso protegido en este módulo.
@endsection

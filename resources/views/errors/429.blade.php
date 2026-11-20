@extends('errors.layout')

@section('title', 'Límite de Peticiones Superado')
@section('code', '429')
@section('icon')
    <i class="fa-solid fa-gauge-simple-high"></i>
@endsection
@section('message')
    Has enviado demasiadas solicitudes al servidor en un breve lapso de tiempo. Por razones de seguridad y estabilidad, espera unos instantes antes de reintentar.
@endsection

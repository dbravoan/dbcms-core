@extends('admin.layout')
@section('title', 'Revisar acción del CMS')
@section('content')
@include('admin.orchestration.panel', ['initialExecutionId' => $executionId])
@endsection

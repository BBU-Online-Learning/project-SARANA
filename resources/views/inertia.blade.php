@extends('layouts.app')
@section('title', $page['props']['title'] ?? 'Workspace')
@section('bodyClass', 'class-page')
@section('inertiaHead')
    @inertiaHead
@endsection
@section('content')
    @inertia
@endsection
@section('scripts')
    @vite('resources/js/inertia.jsx')
@endsection

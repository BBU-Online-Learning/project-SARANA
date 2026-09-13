@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-container workspace-page role-dashboard">
    @php
        $dashboardView = match ($user->role->name) {
            'super_admin' => 'dashboard.super-admin',
            'admin' => 'dashboard.admin',
            'teacher' => 'dashboard.teacher',
            default => 'dashboard.student',
        };
    @endphp
    @include($dashboardView)
</div>
@endsection

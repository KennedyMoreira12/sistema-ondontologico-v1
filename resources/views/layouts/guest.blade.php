<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') — @endif{{ config('app.name', 'LJ SmartClinic') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="sco-auth">
    <div class="sco-auth-card">
        <div class="sco-auth-brand">
            <img class="sco-auth-logo" src="{{ asset('img/lj_logo.png') }}" alt="{{ config('app.name', 'LJ SmartClinic') }}">
            <p>{{ config('app.tagline', 'Plataforma de gestão para clínicas') }}</p>
        </div>

        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset

        @if (Route::has('painel.dashboard'))
            <a class="sco-auth-panel-link" href="{{ route('painel.dashboard') }}">Painel da Plataforma</a>
        @endif
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'SCO')) — {{ config('app.name', 'SCO') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="w-100" style="max-width: 420px;">
            <div class="text-center mb-4">
                <div class="display-5 text-primary"><i class="bi bi-hospital"></i></div>
                <h1 class="h4 mt-2">{{ config('app.name', 'SCO') }}</h1>
                <p class="text-muted">Sistema de Consultório Odontológico</p>
            </div>

            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </div>
    </div>
</body>
</html>
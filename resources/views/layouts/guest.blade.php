<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') — Smart Farm SPK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-[var(--color-gray-50)] font-primary text-[var(--color-gray-900)]">
    <div class="min-h-full flex items-center justify-center px-4 py-12">
        @yield('content')
    </div>

    {{-- Toast Notifications --}}
    @include('components.toast')

    @stack('scripts')
</body>
</html>

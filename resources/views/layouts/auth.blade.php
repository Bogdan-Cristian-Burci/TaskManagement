<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Task Management') }} - @yield('title')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles -->
    @vite('resources/css/app.css')

    <!-- Scripts -->
    @vite('resources/js/app.js')

    <!-- Custom Styles -->
    <style>
        body {
            background-color: #f9fafb;
            background-image:
                radial-gradient(at 40% 20%, rgba(99, 102, 241, 0.1) 0px, transparent 50%),
                radial-gradient(at 80% 0%, rgba(99, 102, 241, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 50%, rgba(99, 102, 241, 0.1) 0px, transparent 50%);
            background-size: 100% 100%;
            background-attachment: fixed;
        }
        .auth-card {
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.8);
        }
    </style>

    @yield('styles')
</head>
<body class="font-sans antialiased">
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md auth-card rounded-xl shadow-lg opacity-0" id="authCard">
        <div class="flex justify-center py-6">
            <a href="{{ url('/') }}" class="text-primary-600 text-3xl font-bold">
                {{ config('app.name', 'Task Management') }}
            </a>
        </div>

        <div class="px-8 pb-8">
            @yield('content')
        </div>
    </div>
</div>

<script>
    // Initial Animation
    document.addEventListener('DOMContentLoaded', function() {
        gsap.to("#authCard", {
            opacity: 1,
            y: -20,
            duration: 0.8,
            ease: "power2.out"
        });

        @yield('scripts')
    });
</script>
</body>
</html>

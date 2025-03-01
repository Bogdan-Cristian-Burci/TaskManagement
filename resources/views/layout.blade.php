<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Colab Core</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
    @stack('scripts')   {{-- This is where the scripts will be pushed --}}

</head>
<body class="font-sans bg-colab-background text-colab-text overscroll-none h-screen" id="smooth-wrapper">
    @include('includes.header')
    @yield('content')
    @include('includes.footer')
</body>
</html>

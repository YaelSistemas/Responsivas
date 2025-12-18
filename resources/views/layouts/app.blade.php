@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Título dinámico de la pestaña --}}
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon-vysisa.png') }}">
    {{-- Si usas PNG, podrías usar esto en su lugar: --}}
    {{-- <link rel="icon" type="image/png" href="{{ asset('favicon-32x32.png') }}"> --}}

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen flex flex-col">

    {{-- CONTENIDO PRINCIPAL (ocupa todo el alto disponible) --}}
    <div class="flex-1">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>

    {{-- FOOTER GLOBAL --}}
    <footer class="bg-gray-100 border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4
                    text-xs sm:text-sm text-gray-500
                    flex flex-col sm:flex-row items-center justify-between gap-2">
            <p class="text-center sm:text-left">
                © 2025 <span class="font-semibold">GRUPO VYSISA™</span>. Todos los derechos reservados.
            </p>

            <div class="flex items-center gap-4">
                {{-- Cambia los href cuando tengas las páginas/rutas --}}
                <a href="#" class="hover:text-gray-700 underline underline-offset-4">
                    Contacto
                </a>
                <a href="#" class="hover:text-gray-700 underline underline-offset-4">
                    Política de Privacidad
                </a>
            </div>
        </div>
    </footer>

</body>
</html>

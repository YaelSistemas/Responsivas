<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Laravel'))</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon-vysisa.png') }}">
    {{-- o si usas PNG --}}
    {{-- <link rel="icon" type="image/png" href="{{ asset('favicon-32x32.png') }}"> --}}

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">

    <style>
        .top-bar{display:flex;justify-content:space-between;align-items:center;background:#ffffff;color:#111827;padding:.5rem 1rem;border-bottom:1px solid #e5e7eb}
        .top-bar img{height:40px}
        .user-menu{position:relative;cursor:pointer;display:flex;align-items:center}
        .user-info{display:flex;flex-direction:column;align-items:flex-end;line-height:1.2;padding:.25rem .5rem;border-radius:.375rem}
        .user-info:hover{background:#f3f4f6}
        .user-name{font-weight:700;font-size:14px;color:#111827}
        .user-role{font-size:12px;color:#6b7280}
        .user-dropdown{display:none;position:absolute;top:100%;right:0;margin-top:.25rem;background:#fff;list-style:none;padding:.5rem 0;margin:0;min-width:200px;z-index:1100;box-shadow:0 4px 12px rgba(0,0,0,.18);border-radius:8px;border:1px solid #e5e7eb;opacity:0;transform:translateY(8px);transition:all .2s ease}
        .user-menu:hover .user-dropdown{display:block;opacity:1;transform:translateY(0)}
        .logout-btn{display:block;width:100%;text-align:left;padding:.6rem 1rem;color:#1f2937;text-decoration:none;background:none;border:none;cursor:pointer;font-size:14px;transition:background .15s ease,color .15s ease}
        .logout-btn:hover{background:#fee2e2;color:#dc2626}

        .nav-bar{background:#e62623}
        .nav-inner{display:flex;align-items:center}
        .nav-left,.nav-right{display:flex;list-style:none;margin:0;padding:0}
        .nav-right{margin-left:auto}
        .nav-bar li{position:relative}
        .nav-bar li>a{display:block;padding:.75rem 1rem;color:#fff;text-decoration:none;transition:background .15s ease}
        .nav-bar li:hover>a{background:#b91c1c}

        .nav-bar li ul{display:none;position:absolute;top:100%;left:0;right:auto;background:#fff;list-style:none;margin:0;padding:.5rem 0;min-width:200px;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.18);border-radius:8px;border:1px solid #e5e7eb;opacity:0;transform:translateY(8px);transition:all .2s ease}
        .nav-bar li:hover>ul{display:block;opacity:1;transform:translateY(0)}
        .nav-bar li ul a{padding:.55rem 1rem;color:#1f2937;text-decoration:none;transition:background .15s ease,color .15s ease}
        .nav-bar li ul a:hover{background:#f3f4f6;color:#dc2626}
        .nav-right>li>ul{left:auto;right:0}
    </style>
</head>
<body class="antialiased bg-gray-100">

    <div class="top-bar">
        <div class="logo">
            <a href="{{ route('admin.dashboard') }}">
                <img src="{{ asset('images/logos/vysisa.png') }}" alt="Logo">
            </a>
        </div>

        @auth
        <div class="user-menu">
            <div class="user-info">
                <span class="user-name">{{ Auth::user()->name }}</span>
                <span class="user-role">{{ Auth::user()->roles->first()->display_name ?? 'Usuario' }}</span>
            </div>
            <ul class="user-dropdown">
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">Cerrar Sesión</button>
                    </form>
                </li>
            </ul>
        </div>
        @endauth
    </div>

    <nav class="nav-bar">
        <div class="nav-inner">
            <!-- Izquierda -->
            <ul class="nav-left">
                <!-- Usuarios (sin submenú, directo a index) -->
                <li>
                    <a href="{{ route('admin.users.index') }}">Usuarios</a>
                </li>

                <!-- Permisos (independiente, ajusta la ruta si ya la tienes) -->
                <li>
                    {{-- cambia '#' por route('admin.permissions.index') si existe --}}
                    <a href="{{ route('admin.roles.index') }}">Roles</a>
                </li>

                <!-- Empresa (con submenú) -->
                <li>
                    <a href="{{ route('admin.empresas.index') }}">Empresas</a>
                </li>
            </ul>

            <!-- Derecha -->
            <ul class="nav-right">
                <li>
                    <a href="{{ route('dashboard') }}"><i class="fas fa-arrow-left"></i> Volver al Sistema</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="p-6">
        @yield('content')
    </main>

</body>
</html>

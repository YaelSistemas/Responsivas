<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <style>
        /* Paneles de submenú (dropdowns) */
        .menu-panel{
            position:absolute;z-index:50;margin-top:.5rem;width:12rem;background:#fff;
            border:1px solid #e5e7eb;border-radius:.5rem;box-shadow:0 8px 20px rgba(0,0,0,.12);
        }
        /* Ítems genéricos de submenú */
        .menu-item{
            display:block;width:100%;text-align:left;padding:.55rem 1rem;border:0;background:transparent;
            color:#374151;font-size:.875rem;border-radius:.375rem;cursor:pointer;
            transition:background-color .15s ease,color .15s ease;
        }
        .menu-item:hover{ background:#f3f4f6; color:#111827; }
        .menu-item:focus-visible{ outline:2px solid #2563eb; outline-offset:2px; }

        /* Variante peligro (logout) */
        .menu-item--danger:hover{ background:#fee2e2; color:#dc2626; }

        /* Item activo dentro del submenú */
        .menu-item--active{ color:#4f46e5; font-weight:600; }

        /* Botón del perfil: nombre + rol en una línea */
        .user-chip{ display:inline-flex; gap:.4rem; align-items:center; }
        .user-chip__role{ color:#6b7280; font-weight:500; }
    </style>

    @php
        use Illuminate\Support\Str;

        $empresaActiva = session('empresa_activa', Auth::user()->empresa_id);
        $empresaActual = \App\Models\Empresa::select('id','nombre')->find($empresaActiva);

        $slug = $empresaActual ? Str::slug($empresaActual->nombre) : null;

        // Busca un archivo existente por extensión
        $exts = ['png','svg','jpg','jpeg','webp'];
        $logoRel = null;

        if ($slug) {
            foreach ($exts as $ext) {
                $candidate = "images/logos/{$slug}.{$ext}";
                if (file_exists(public_path($candidate))) {
                    $logoRel = $candidate;
                    break;
                }
            }
        }

        // Fallback si no existe un logo para esa empresa
        $logoUrl = $logoRel ? asset($logoRel) : asset('images/logo.png');
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo (dinámico por empresa activa) -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{ $logoUrl }}" alt="Logo" class="h-9 w-auto">
                    </a>
                </div>

                <!-- Enlaces principales -->
                <div class="hidden sm:flex space-x-8 sm:ms-10 items-center">
                    <div class="relative">
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                                  transition duration-150 ease-in-out
                                  {{ request()->routeIs('dashboard')
                                        ? 'border-indigo-500 text-gray-900'
                                        : 'text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent' }}">
                            {{ __('Dashboard') }}
                        </a>
                    </div>

                    {{-- RH (submenu con Colaboradores, Unidades, Áreas, Puestos, Subsidiarias) --}}
                    @php
                        // Activo si estás en alguna ruta del módulo RH (ya sin prefijo admin)
                        $isRhActive = request()->routeIs(
                            'colaboradores.*','unidades.*','areas.*','puestos.*','subsidiarias.*'
                        );

                        // ¿Tiene al menos UN permiso de RH? (si no, ocultamos todo el bloque RH)
                        $canRH = auth()->user()->canany([
                            'colaboradores.view',
                            'unidades.view','areas.view','puestos.view','subsidiarias.view',
                        ]);
                    @endphp

                    @if($canRH)
                    <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                        <button type="button"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                                        transition duration-150 ease-in-out
                                        {{ $isRhActive
                                            ? 'border-indigo-500 text-gray-900'
                                            : 'text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent' }}">
                            RH
                        </button>

                        <div x-show="open" x-transition class="menu-panel">
                            <div class="p-2">
                                @can('colaboradores.view')
                                    <a href="{{ route('colaboradores.index') }}"
                                        class="menu-item {{ request()->routeIs('colaboradores.*') ? 'menu-item--active' : '' }}">
                                        Colaboradores
                                    </a>
                                @endcan

                                @can('unidades.view')
                                    <a href="{{ route('unidades.index') }}"
                                        class="menu-item {{ request()->routeIs('unidades.*') ? 'menu-item--active' : '' }}">
                                        Unidades de servicio
                                    </a>
                                @endcan

                                @can('areas.view')
                                    <a href="{{ route('areas.index') }}"
                                        class="menu-item {{ request()->routeIs('areas.*') ? 'menu-item--active' : '' }}">
                                        Áreas
                                    </a>
                                @endcan

                                @can('puestos.view')
                                    <a href="{{ route('puestos.index') }}"
                                        class="menu-item {{ request()->routeIs('puestos.*') ? 'menu-item--active' : '' }}">
                                        Puestos
                                    </a>
                                @endcan

                                @can('subsidiarias.view')
                                    <a href="{{ route('subsidiarias.index') }}"
                                        class="menu-item {{ request()->routeIs('subsidiarias.*') ? 'menu-item--active' : '' }}">
                                        Subsidiarias
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- INVENTARIO (submenu con Productos) --}}
@php
    $isInvActive = request()->routeIs('productos.*');
@endphp

@canany(['productos.view','productos.create','productos.edit','productos.delete'])
<div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
    <button type="button"
            class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                   transition duration-150 ease-in-out
                   {{ $isInvActive
                        ? 'border-indigo-500 text-gray-900'
                        : 'text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent' }}">
        Inventario
    </button>

    <div x-show="open" x-transition class="menu-panel">
        <div class="p-2">
            @can('productos.view')
                <a href="{{ route('productos.index') }}"
                   class="menu-item {{ request()->routeIs('productos.*') ? 'menu-item--active' : '' }}">
                    Productos
                </a>
            @endcan
        </div>
    </div>
</div>
@endcanany

                </div>
            </div>

            <!-- Lado derecho (Admin + Empresa + Perfil) -->
            <div class="hidden sm:flex sm:items-center sm:ms-10 space-x-8">
                <!-- ADMIN (solo si es admin) -->
                @if(Auth::user()->hasRole('Administrador'))
                    <div class="relative">
                        <a href="{{ route('admin.dashboard') }}"
                           class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                                  focus:outline-none transition duration-150 ease-in-out
                                  text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent">
                            {{ __('Panel Admin') }}
                        </a>
                    </div>
                @endif

                <!-- EMPRESA (selector) -->
                @if(Auth::user()->hasRole('Administrador'))
                    @php
                        $empresas = \App\Models\Empresa::all();
                        $empresaActiva = session('empresa_activa', Auth::user()->empresa_id);
                    @endphp
                    <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                        <div class="flex items-center">
                            <button type="button"
                                    class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                                           focus:outline-none transition duration-150 ease-in-out
                                           text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent">
                                {{ strtoupper($empresas->firstWhere('id', $empresaActiva)?->nombre ?? 'EMPRESA') }}
                            </button>
                        </div>
                        <div x-show="open" x-transition class="menu-panel">
                            <form method="POST" action="{{ route('admin.cambiarEmpresa') }}" class="p-2">
                                @csrf
                                @foreach($empresas as $empresa)
                                    <button type="submit"
                                            name="empresa_id"
                                            value="{{ $empresa->id }}"
                                            class="menu-item {{ (int)$empresaActiva === (int)$empresa->id ? 'menu-item--active' : '' }}">
                                        {{ strtoupper($empresa->nombre) }}
                                    </button>
                                @endforeach
                            </form>
                        </div>
                    </div>
                @endif

                <!-- PERFIL -->
                <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                    <div class="flex items-center">
                        <button type="button"
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium
                                       rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <span class="user-chip">
                                <span>{{ Auth::user()->name }}</span>
                                <span class="user-chip__role">
                                    ({{ Auth::user()->roles->first()->display_name ?? 'Usuario' }})
                                </span>
                            </span>
                        </button>
                    </div>
                    <div x-show="open" x-transition class="menu-panel right-0">
                        <form method="POST" action="{{ route('logout') }}" class="p-2">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();"
                                class="menu-item menu-item--danger">
                                {{ __('Cerrar Sesion') }}
                            </x-dropdown-link>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Menú hamburguesa -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500
                               hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</nav>

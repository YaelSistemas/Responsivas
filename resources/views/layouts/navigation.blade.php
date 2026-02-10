<nav x-data="{ open: false }" @keydown.window.escape="open=false" class="bg-white border-b border-gray-100 relative">
  <style>
    /* Paneles de submenú (desktop dropdowns) */
    .menu-panel{
      position:absolute;z-index:50;margin-top:.5rem;width:12rem;background:#fff;
      border:1px solid #e5e7eb;border-radius:.5rem;box-shadow:0 8px 20px rgba(0,0,0,.12);
    }
    .menu-item{
      display:block;width:100%;text-align:left;padding:.55rem 1rem;border:0;background:transparent;
      color:#374151;font-size:.875rem;border-radius:.375rem;cursor:pointer;
      transition:background-color .15s ease,color .15s ease;
    }
    .menu-item:hover{ background:#f3f4f6; color:#111827; }
    .menu-item:focus-visible{ outline:2px solid #2563eb; outline-offset:2px; }
    .menu-item--danger:hover{ background:#fee2e2; color:#dc2626; }
    .menu-item--active{ color:#4f46e5; font-weight:600; }
    .user-chip{ display:inline-flex; gap:.4rem; align-items:center; }
    .user-chip__role{ color:#6b7280; font-weight:500; }

    /* ====== MÓVIL ====== */
    .mobile-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:60; }
    .mobile-drawer{
      position:fixed; top:0; left:0; height:100%; width:22rem; max-width:90vw;
      background:#fff; box-shadow: 12px 0 24px rgba(0,0,0,.2); z-index:70;
      display:flex; flex-direction:column;
    }
    .mobile-drawer__header{ display:flex; align-items:center; justify-content:space-between; padding:.85rem 1rem; border-bottom:1px solid #e5e7eb; }
    .mobile-drawer__body{ padding:.75rem 1rem 1rem; overflow-y:auto; -webkit-overflow-scrolling:touch; }

    .mobile-link{
      display:flex; align-items:center; justify-content:space-between;
      width:100%; padding:.65rem .75rem; border-radius:.5rem; font-size:.95rem;
      color:#111827; background:transparent; border:0; cursor:pointer;
    }
    .mobile-link:hover{ background:#f3f4f6; }
    .mobile-sub{ padding-left:.75rem; margin:.25rem 0 .5rem; }
    .mobile-a{ display:block; padding:.55rem .65rem; border-radius:.5rem; font-size:.92rem; color:#374151; }
    .mobile-a:hover{ background:#f3f4f6; color:#111827; }
    .mobile-a--active{ color:#4f46e5; font-weight:600; }
    .btn-icon{ display:inline-flex; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:.5rem; }
    .btn-icon:hover{ background:#f3f4f6; }

    [x-cloak]{ display:none !important; }
  </style>

  @php
    use Illuminate\Support\Str;

    $empresaActiva = session('empresa_activa', Auth::user()->empresa_id);
    $empresaActual = \App\Models\Empresa::select('id','nombre')->find($empresaActiva);

    $slug = $empresaActual ? Str::slug($empresaActual->nombre) : null;

    $exts = ['png','svg','jpg','jpeg','webp'];
    $logoRel = null;

    if ($slug) {
      foreach ($exts as $ext) {
        $candidate = "images/logos/{$slug}.{$ext}";
        if (file_exists(public_path($candidate))) {
          $logoRel = $candidate; break;
        }
      }
    }
    $logoUrl = $logoRel ? asset($logoRel) : asset('images/logo.png');

    $isRhActive       = request()->routeIs('colaboradores.*','unidades.*','areas.*','puestos.*','subsidiarias.*');
    $isProductosActive= request()->routeIs('productos.*');
    $isFormatosActive = request()->routeIs('responsivas.*', 'devoluciones.*', 'cartuchos.*')
      || request()->is('celulares/*');

    // COMPRAS: marcar activo si estoy en oc.* o proveedores.*
    $isComprasActive  = request()->routeIs('oc.*','proveedores.*');

    // ===== permisos Compras (nuevo) =====
    $canOC       = auth()->user()->can('oc.view');
    $canProv     = auth()->user()->can('proveedores.view');
    $canCompras  = $canOC || $canProv; // oculta todo el menú si no tiene ninguno

    $canFormatos = auth()->user()->canany(['responsivas.view','devoluciones.view','cartuchos.view','celulares.view',]);
  @endphp

  <div x-effect="document.body.style.overflow = open ? 'hidden' : ''"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16">
      <div class="flex items-center">
        <!-- Logo -->
        <div class="shrink-0 flex items-center">
          <a href="{{ route('dashboard') }}">
            <img src="{{ $logoUrl }}" alt="Logo" class="h-9 w-auto">
          </a>
        </div>

        <!-- Enlaces principales (desktop) -->
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

          @php
            $canRH = auth()->user()->canany([
              'colaboradores.view','unidades.view','areas.view','puestos.view','subsidiarias.view',
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

          @canany(['productos.view','productos.create','productos.edit','productos.delete'])
          <div class="relative">
            <a href="{{ route('productos.index') }}"
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                      transition duration-150 ease-in-out
                      {{ $isProductosActive
                            ? 'border-indigo-500 text-gray-900'
                            : 'text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent' }}">
              Productos
            </a>
          </div>
          @endcanany

          @if($canFormatos)
            <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
              <button type="button"
                      class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                            transition duration-150 ease-in-out
                            {{ $isFormatosActive
                                  ? 'border-indigo-500 text-gray-900'
                                  : 'text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent' }}">
                Formatos
              </button>

              <div x-show="open" x-transition class="menu-panel">
                <div class="p-2">
                  @can('responsivas.view')
                    <a href="{{ route('responsivas.index') }}"
                      class="menu-item {{ request()->routeIs('responsivas.*') ? 'menu-item--active' : '' }}">
                      Responsivas
                    </a>
                  @endcan

                  @can('devoluciones.view')
                    <a href="{{ route('devoluciones.index') }}"
                      class="menu-item {{ request()->routeIs('devoluciones.*') ? 'menu-item--active' : '' }}">
                      Devoluciones
                    </a>
                  @endcan

                  @can('celulares.view')
                    <a href="{{ url('/celulares/responsivas') }}"
                      class="menu-item {{ request()->is('celulares/responsivas*') ? 'menu-item--active' : '' }}">
                      Bitácora de celulares
                    </a>
                  @endcan

                  @can('cartuchos.view')
                    <a href="{{ route('cartuchos.index') }}"
                      class="menu-item {{ request()->routeIs('cartuchos.*') ? 'menu-item--active' : '' }}">
                      Entrega de Cartuchos
                    </a>
                  @endcan
                </div>
              </div>
            </div>
          @endif

          {{-- ===== COMPRAS (desktop) ===== --}}
          @if($canCompras)
            <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
              <button type="button"
                      class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5
                             transition duration-150 ease-in-out
                             {{ $isComprasActive
                                  ? 'border-indigo-500 text-gray-900'
                                  : 'text-gray-500 hover:text-gray-700 hover:border-gray-300 border-transparent' }}">
                Compras
              </button>

              <div x-show="open" x-transition class="menu-panel">
                <div class="p-2">
                  @can('oc.view')
                    <a href="{{ route('oc.index') }}"
                       class="menu-item {{ request()->routeIs('oc.*') ? 'menu-item--active' : '' }}">
                      Órdenes de compra
                    </a>
                  @endcan
                  @can('proveedores.view')
                    <a href="{{ route('proveedores.index') }}"
                       class="menu-item {{ request()->routeIs('proveedores.*') ? 'menu-item--active' : '' }}">
                      Proveedores
                    </a>
                  @endcan
                </div>
              </div>
            </div>
          @endif
          {{-- ===== /COMPRAS ===== --}}
        </div>
      </div>

      <!-- Lado derecho (desktop) -->
      <div class="hidden sm:flex sm:items-center sm:ms-10 space-x-8">
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

      <!-- Botón hamburguesa (móvil) -->
      <div class="-me-2 flex items-center sm:hidden">
        <button @click="open = ! open"
                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500
                       hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                aria-label="Abrir menú">
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

  <!-- ======= CONTENIDO DEL MENÚ MÓVIL ======= -->
  <div x-cloak x-show="open" x-transition.opacity class="mobile-backdrop sm:hidden" @click="open=false"></div>

  <div x-cloak x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="-translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="-translate-x-full"
       class="mobile-drawer sm:hidden">
    <div class="mobile-drawer__header">
      <div class="flex items-center gap-2">
        <img src="{{ $logoUrl }}" alt="Logo" class="h-7 w-auto">
        <span class="text-sm text-gray-500"></span>
      </div>
      <button class="btn-icon text-gray-500" @click="open=false" aria-label="Cerrar menú">✕</button>
    </div>

    <div class="mobile-drawer__body" x-data="{ rh:false, formatos:false, compras:false, empresa:false, perfil:false }">
      <!-- Dashboard -->
      <a href="{{ route('dashboard') }}"
         class="mobile-a {{ request()->routeIs('dashboard') ? 'mobile-a--active' : '' }}">
        Dashboard
      </a>

      <!-- RH -->
      @if($canRH)
      <div class="mt-1">
        <button class="mobile-link" @click="rh = !rh" :aria-expanded="rh">
          <span>RH</span>
          <svg :class="{'rotate-180': rh}" class="h-4 w-4 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 011.06.02L10 10.586l3.71-3.356a.75.75 0 111.02 1.1l-4.22 3.817a.75.75 0 01-1.02 0L5.21 8.33a.75.75 0 01.02-1.12z"/></svg>
        </button>
        <div x-show="rh" x-transition class="mobile-sub">
          @can('colaboradores.view')
          <a href="{{ route('colaboradores.index') }}"
             class="mobile-a {{ request()->routeIs('colaboradores.*') ? 'mobile-a--active' : '' }}">
            Colaboradores
          </a>
          @endcan
          @can('unidades.view')
          <a href="{{ route('unidades.index') }}"
             class="mobile-a {{ request()->routeIs('unidades.*') ? 'mobile-a--active' : '' }}">
            Unidades de servicio
          </a>
          @endcan
          @can('areas.view')
          <a href="{{ route('areas.index') }}"
             class="mobile-a {{ request()->routeIs('areas.*') ? 'mobile-a--active' : '' }}">
            Áreas
          </a>
          @endcan
          @can('puestos.view')
          <a href="{{ route('puestos.index') }}"
             class="mobile-a {{ request()->routeIs('puestos.*') ? 'mobile-a--active' : '' }}">
            Puestos
          </a>
          @endcan
          @can('subsidiarias.view')
          <a href="{{ route('subsidiarias.index') }}"
             class="mobile-a {{ request()->routeIs('subsidiarias.*') ? 'mobile-a--active' : '' }}">
            Subsidiarias
          </a>
          @endcan
        </div>
      </div>
      @endif

      <!-- Productos -->
      @canany(['productos.view','productos.create','productos.edit','productos.delete'])
      <a href="{{ route('productos.index') }}" class="mobile-a {{ $isProductosActive ? 'mobile-a--active' : '' }}">
        Productos
      </a>
      @endcanany

      <!-- Formatos (MÓVIL) -->
      @php
        // ✅ Mostrar “Formatos” si tiene AL MENOS 1 permiso del bloque
        $canFormatos = auth()->user()->canany([
          'responsivas.view',
          'devoluciones.view',
          'cartuchos.view',
          'celulares.view',
        ]);
      @endphp

      @if($canFormatos)
        <div class="mt-1">
          <button class="mobile-link" @click="formatos = !formatos" :aria-expanded="formatos">
            <span>Formatos</span>
            <svg :class="{'rotate-180': formatos}" class="h-4 w-4 transition-transform" viewBox="0 0 20 20" fill="currentColor">
              <path d="M5.23 7.21a.75.75 0 011.06.02L10 10.586l3.71-3.356a.75.75 0 111.02 1.1l-4.22 3.817a.75.75 0 01-1.02 0L5.21 8.33a.75.75 0 01.02-1.12z"/>
            </svg>
          </button>

          <div x-show="formatos" x-transition class="mobile-sub">
            @can('responsivas.view')
              <a href="{{ route('responsivas.index') }}"
                class="mobile-a {{ request()->routeIs('responsivas.*') ? 'mobile-a--active' : '' }}">
                Responsivas
              </a>
            @endcan

            @can('devoluciones.view')
              <a href="{{ route('devoluciones.index') }}"
                class="mobile-a {{ request()->routeIs('devoluciones.*') ? 'mobile-a--active' : '' }}">
                Devoluciones
              </a>
            @endcan

            @can('celulares.view')
              <a href="{{ url('/celulares/responsivas') }}"
                class="mobile-a {{ request()->is('celulares/responsivas*') ? 'mobile-a--active' : '' }}">
                Bitácora de celulares
              </a>
            @endcan

            @can('cartuchos.view')
              <a href="{{ route('cartuchos.index') }}"
                class="mobile-a {{ request()->routeIs('cartuchos.*') ? 'mobile-a--active' : '' }}">
                Cartuchos
              </a>
            @endcan
          </div>
        </div>
      @endif

      {{-- ===== COMPRAS (móvil) ===== --}}
      @if($canCompras)
        <div class="mt-1">
          <button class="mobile-link" @click="compras = !compras" :aria-expanded="compras">
            <span>Compras</span>
            <svg :class="{'rotate-180': compras}" class="h-4 w-4 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 011.06.02L10 10.586l3.71-3.356a.75.75 0 111.02 1.1l-4.22 3.817a.75.75 0 01-1.02 0L5.21 8.33a.75.75 0 01.02-1.12z"/></svg>
          </button>
          <div x-show="compras" x-transition class="mobile-sub">
            @can('oc.view')
              <a href="{{ route('oc.index') }}"
                 class="mobile-a {{ request()->routeIs('oc.*') ? 'mobile-a--active' : '' }}">
                Órdenes de compra
              </a>
            @endcan
            @can('proveedores.view')
              <a href="{{ route('proveedores.index') }}"
                 class="mobile-a {{ request()->routeIs('proveedores.*') ? 'mobile-a--active' : '' }}">
                Proveedores
              </a>
            @endcan
          </div>
        </div>
      @endif
      {{-- ===== /COMPRAS ===== --}}

      <hr class="my-3 border-gray-200">

      @if(Auth::user()->hasRole('Administrador'))
      <a href="{{ route('admin.dashboard') }}" class="mobile-a">
        {{ __('Panel Admin') }}
      </a>
      @endif

      @if(Auth::user()->hasRole('Administrador'))
        @php
          $empresas = \App\Models\Empresa::all();
          $empresaActiva = session('empresa_activa', Auth::user()->empresa_id);
        @endphp
        <div class="mt-1">
          <button class="mobile-link" @click="empresa = !empresa" :aria-expanded="empresa">
            <span>Empresa</span>
            <svg :class="{'rotate-180': empresa}" class="h-4 w-4 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 011.06.02L10 10.586l3.71-3.356a.75.75 0 111.02 1.1l-4.22 3.817a.75.75 0 01-1.02 0L5.21 8.33a.75.75 0 01.02-1.12z"/></svg>
          </button>
          <div x-show="empresa" x-transition class="mobile-sub">
            <form method="POST" action="{{ route('admin.cambiarEmpresa') }}">
              @csrf
              @foreach($empresas as $empresa)
                <button type="submit" name="empresa_id" value="{{ $empresa->id }}"
                        class="mobile-a {{ (int)$empresaActiva === (int)$empresa->id ? 'mobile-a--active' : '' }}">
                  {{ strtoupper($empresa->nombre) }}
                </button>
              @endforeach
            </form>
          </div>
        </div>
      @endif

      <div class="mt-1">
        <button class="mobile-link" @click="perfil = !perfil" :aria-expanded="perfil">
          <span>{{ Auth::user()->name }} <span class="text-gray-500">({{ Auth::user()->roles->first()->display_name ?? 'Usuario' }})</span></span>
          <svg :class="{'rotate-180': perfil}" class="h-4 w-4 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 011.06.02L10 10.586l3.71-3.356a.75.75 0 111.02 1.1l-4.22 3.817a.75.75 0 01-1.02 0L5.21 8.33a.75.75 0 01.02-1.12z"/></svg>
        </button>
        <div x-show="perfil" x-transition class="mobile-sub">
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-dropdown-link :href="route('logout')"
                onclick="event.preventDefault(); this.closest('form').submit();"
                class="mobile-a text-red-600 hover:text-red-700">
              {{ __('Cerrar Sesion') }}
            </x-dropdown-link>
          </form>
        </div>
      </div>
    </div>
  </div>
</nav>

@extends('layouts.admin')

@section('title', 'Empresas')

@section('content')
<style>
    .company-table th,
    .company-table td {
        text-align: center;
        vertical-align: middle;
    }
</style>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">

    {{-- Título --}}
    <h2 class="font-semibold text-xl text-gray-800 leading-tight text-center mb-6">
        {{ __('Empresas') }}
    </h2>

    {{-- Botón crear empresa (solo si existe la ruta) --}}
    @if (Route::has('admin.empresas.create'))
        <a href="{{ route('admin.empresas.create') }}"
           style="display:inline-block; margin-bottom: 1.5rem; background-color: #2563eb; color: white; font-weight: 600;
                  padding: 0.5rem 1rem; border-radius: 0.375rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-decoration: none;">
            Nueva Empresa
        </a>
    @endif

    {{-- Mensajes de alerta --}}
    @if (session('created'))
        <div id="success-alert" class="mt-4 mb-6 p-4 bg-green-100 text-green-800 border border-green-300 rounded text-center">
            ✅ Empresa creada correctamente.
        </div>
    @endif

    @if (session('deleted'))
        <div id="deleted-alert" class="mt-4 mb-6 p-4 bg-red-100 text-red-800 border border-red-300 rounded text-center">
            🗑️ Empresa eliminada correctamente.
        </div>
    @endif

    @if (session('updated'))
        <div id="updated-alert" class="mt-4 mb-6 p-4 bg-blue-100 text-blue-800 border border-blue-300 rounded text-center">
            ✏️ Empresa actualizada correctamente.
        </div>
    @endif

    <script>
        setTimeout(() => {
            document.querySelectorAll('#success-alert, #deleted-alert, #updated-alert')
                .forEach(alert => {
                    if (alert) {
                        alert.classList.add('opacity-0');
                        setTimeout(() => alert.remove(), 500);
                    }
                });
        }, 3000);
    </script>

    {{-- Tabla --}}
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mx-auto w-full max-w-5xl">
        <table class="table-auto w-full text-sm company-table">
            <thead>
                <tr>
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Nombre</th>
                    <th class="px-4 py-2">RFC</th>
                    <th class="px-4 py-2">Creada</th>
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($empresas as $e)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ $e->id }}</td>
                        <td class="px-4 py-2">{{ $e->nombre }}</td>
                        <td class="px-4 py-2">{{ $e->rfc ?? '—' }}</td>
                        <td class="px-4 py-2">{{ optional($e->created_at)->format('Y-m-d') }}</td>
                        <td class="px-4 py-2 text-center">
                            <div class="flex justify-center items-center gap-4">

                                {{-- Ver --}}
                                @if (Route::has('admin.empresas.show'))
                                    <a href="{{ route('admin.empresas.show', $e) }}"
                                       class="text-indigo-600 hover:text-indigo-800 text-lg"
                                       title="Ver empresa">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                @endif

                                {{-- Editar --}}
                                @if (Route::has('admin.empresas.edit'))
                                    <a href="{{ route('admin.empresas.edit', $e) }}"
                                       class="text-yellow-500 hover:text-yellow-700 text-lg"
                                       title="Editar empresa">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                @endif

                                {{-- Eliminar --}}
                                @if (Route::has('admin.empresas.destroy'))
                                    <form action="{{ route('admin.empresas.destroy', $e) }}"
                                          method="POST"
                                          onsubmit="return confirm('¿Estás seguro de eliminar esta empresa?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar empresa" class="text-red-600 hover:text-red-800 text-lg">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @endif

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            No hay empresas registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $empresas->links() }}
    </div>
</div>
@endsection

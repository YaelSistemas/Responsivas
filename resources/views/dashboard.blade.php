@php
    use App\Models\Empresa;
    use Illuminate\Support\Facades\Auth;

    $auth = Auth::user();

    // Si es "Administrador", usa la empresa activa de la sesión; si no, la suya
    $empresaId = $auth->hasRole('Administrador')
        ? session('empresa_activa', $auth->empresa_id)
        : $auth->empresa_id;

    $empresa = Empresa::find($empresaId);
@endphp

{{-- Pasamos el título dinámico al layout --}}
<x-app-layout title="Dashboard">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    Estás en el entorno de la empresa:
                    <span class="font-bold text-indigo-600">
                        {{ $empresa->nombre ?? 'Sin empresa asignada' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

{{--
================================================================================
VISTA: dashboard/user.blade.php
--------------------------------------------------------------------------------
Propósito: Panel personal para la usuaria visionadora. Muestra métricas resumidas
                        y acceso a visionados filtrados por estado mediante pestañas cargadas
                        dinámicamente (AJAX).

Elementos clave:
    - Tarjetas de métricas ($stats: total, completados, pendientes, tasa).
    - Navegación de pestañas: pending | in_progress | completed | stats.
    - Contenedor #tab-content se llena vía petición AJAX inicial (data-autoload-url)
        y subsecuentes clicks en enlaces .tab-link (interceptados por JS externo).

Expectativas de datos:
    - $stats array: ['total'=>int,'completados'=>int,'pendientes'=>int,'tasa'=>int]
    - La carga de la tabla se delega a la ruta dashboard.user.tab.

Notas:
    - El marcado inicial no incluye la tabla para reducir HTML inicial.
    - Accesibilidad: aria-current asignado en la pestaña activa inicial.
================================================================================
--}}
@extends('layouts.app')

@section('title', 'Mi Panel de Visionado')
@section('role', 'Visionadora')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-1">Mi Panel de Visionado</h1>
    <p class="mb-6 text-gray-600">Bienvenida, {{ auth()->user()->name }}. Aquí están tus visionados asignados y estadísticas personales.</p>

    @php($s = $stats ?? ['total'=>0,'completados'=>0,'pendientes'=>0,'tasa'=>0])
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Total Asignados</div>
            <div class="text-2xl font-semibold">{{ $s['total'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Completados</div>
            <div class="text-2xl font-semibold text-green-600">{{ $s['completados'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Pendientes</div>
            <div class="text-2xl font-semibold text-yellow-600">{{ $s['pendientes'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Tasa Completado</div>
            <div class="text-2xl font-semibold text-purple-600">{{ $s['tasa'] }}%</div>
        </div>
    </div>

    <div id="tabs-nav" class="flex items-center gap-2 border-b mb-4">
        <a href="{{ route('dashboard.user.tab', ['tab'=>'pending','ajax'=>1]) }}" class="tab-link px-3 py-2 rounded-t bg-gray-100" aria-current="page">Pendientes</a>
        <a href="{{ route('dashboard.user.tab', ['tab'=>'in_progress','ajax'=>1]) }}" class="tab-link px-3 py-2 rounded-t">En Progreso</a>
        <a href="{{ route('dashboard.user.tab', ['tab'=>'completed','ajax'=>1]) }}" class="tab-link px-3 py-2 rounded-t">Completados</a>
        <a href="{{ route('dashboard.user.tab', ['tab'=>'stats','ajax'=>1]) }}" class="tab-link px-3 py-2 rounded-t">Mis Estadísticas</a>
    </div>

    <div id="tab-content" data-autoload-url="{{ route('dashboard.user.tab', ['tab'=>'pending','ajax'=>1]) }}" class="bg-white rounded-lg shadow">
        <!-- Contenido cargado dinámicamente (tabla visionados o estadísticas) -->
    </div>
</div>
@endsection
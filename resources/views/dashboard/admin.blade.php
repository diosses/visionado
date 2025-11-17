{{--
================================================================================
VISTA: dashboard/admin.blade.php
--------------------------------------------------------------------------------
Propósito: Panel principal para rol administrador / coordinador de visionado.

Secciones clave:
 1. Métricas superiores (totales por estado) provistas por el controlador.
 2. Navegación por pestañas (visionados | emisiones | obras) controlada vía ?tab=.
 3. Contenido dinámico de cada pestaña renderizado en el lado servidor (con opción
        futura de recarga parcial usando AJAX sobre el contenedor #tab-content).

Variables esperadas (ejemplos):
    - $totalCompletados, $totalPendientes, $totalEnProgreso, $totalPorAuditar (int)
    - $visionados (colección/paginador para la tabla de visionados)
    - $emisiones, $emisionesGroups, $emisionesByGroup, $visionadoras, $suggestions
    - $obras, catálogos varios: $generosObra, $idiomasCatalogo, $paisesCatalogo, $canales

Notas:
    - La variable $tab se deriva de request('tab','visionados').
    - Cada pestaña incluye un componente Blade especializado.
    - Los modales globales (por ejemplo modal de crear/editar obra) se incluyen al final
        para estar disponibles sin duplicaciones.
================================================================================
--}}
@extends('layouts.app')
@section('title', 'Panel de Control')
@section('content')
@php($tab = request('tab', 'visionados'))
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-2">Panel de Control</h1>
    <p class="text-gray-600 mb-6">Bienvenida, Visionadora Jefa. Aquí puedes gestionar visionados, obras y auditorías.</p>

    <!-- Métricas superiores (conteos agregados) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

        <div class="bg-white rounded-lg shadow-md p-6 flex flex-col items-center justify-center">
            <div class="text-green-500 mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="text-3xl font-bold">{{ $totalCompletados }}</div>
            <div class="text-gray-500">Completadas</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 flex flex-col items-center justify-center">
            <div class="text-yellow-500 mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="text-3xl font-bold">{{ $totalPendientes }}</div>
            <div class="text-gray-500">Pendientes</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 flex flex-col items-center justify-center">
            <div class="text-blue-500 mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <div class="text-3xl font-bold">{{ $totalEnProgreso }}</div>
            <div class="text-gray-500">Asignadas</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 flex flex-col items-center justify-center">
            <div class="text-purple-500 mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </div>
            <div class="text-3xl font-bold">{{ $totalPorAuditar }}</div>
            <div class="text-gray-500">Auditadas</div>
        </div>
    </div>

    <!-- Navegación de pestañas principales -->
    <div class="bg-white rounded-lg shadow-md mb-8 -mx-6">
        <div class="flex justify-center border-b gap-2" id="tabs-nav">
            @php($base = request()->url())
            <a href="{{ $base }}?tab=visionados" data-tab="visionados" class="tab-link px-6 py-3 text-sm font-medium {{ $tab==='visionados' ? 'bg-gray-100 border-b-2 border-blue-500' : '' }}">Visionados</a>
            <a href="{{ $base }}?tab=material" data-tab="material" class="tab-link px-6 py-3 text-sm font-medium {{ $tab==='material' ? 'bg-gray-100 border-b-2 border-blue-500' : '' }}">Emisiones</a>
            <a href="{{ $base }}?tab=obras" data-tab="obras" class="tab-link px-6 py-3 text-sm font-medium {{ $tab==='obras' ? 'bg-gray-100 border-b-2 border-blue-500' : '' }}">Obras</a>
        </div>

        <!-- Contenido de la pestaña activa -->
        <div id="tab-content">
            @section('tab_content')
                @if ($tab === 'visionados')
                    <div class="p-6">
                        <h2 class="text-lg font-semibold mb-4">Visionados recientes</h2>
                        <x-admin.tabla-visionados :visionados="$visionados ?? collect([])" :visionadoras="$visionadoras ?? collect([])" />
                    </div>
                @elseif ($tab === 'material')
                    <div class="p-6 border-t">
                        <h2 class="text-lg font-semibold mb-4">Emisiones</h2>
                        <x-admin.tabla-emisiones :emisiones="$emisiones ?? collect([])" :grupos="$emisionesGroups ?? null" :byGroup="$emisionesByGroup ?? collect([])" :visionadoras="$visionadoras ?? collect([])" :suggestions="$suggestions ?? []" :generosObra="$generosObra ?? []" :idiomasCatalogo="$idiomasCatalogo ?? []" :paisesCatalogo="$paisesCatalogo ?? []" :canales="$canales ?? []" />
                    </div>
                @elseif ($tab === 'obras')
                    <div class="p-6 border-t">
                        <h2 class="text-lg font-semibold mb-4">Obras</h2>
                        <x-tabla-obras :obras="$obras ?? collect([])" :generosObra="$generosObra ?? []" :idiomasCatalogo="$idiomasCatalogo ?? []" :paisesCatalogo="$paisesCatalogo ?? []" />
                    </div>
                @endif
            @show
        </div>
    </div>
</div>
<!-- Modal global reutilizable para creación/edición de obra -->
@include('components.modal-obras', ['id' => 'modal-create-obra', 'obra' => null])

@endsection


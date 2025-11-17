{{--
================================================================================
COMPONENTE: series-wizard.blade.php
--------------------------------------------------------------------------------
Propósito: Modal de dos pasos para crear o editar una "obra general" (serie) y
generar títulos de capítulos a partir de emisiones seleccionadas.

Entradas esperadas (inyectadas desde la vista padre):
        - $emisionIds (array) IDs de emisiones sobre las que se construirá la serie.
        - $serie (opcional) Obra existente en modo edición (precarga datos base).

Flujo UI:
        Paso 1: Seleccionar o crear obra general (typeahead + botón "Crear nueva obra").
                                        Se persiste serie_id en campo oculto.
        Paso 2: Definir patrón de generación de nombres para capítulos (base + sufijo).
                                        Sufijos soportados: fecha, EP correlativo, sin sufijo.
                                        Se muestra vista previa dinámica (#wizard-preview).

Data attributes clave:
        - data-series-wizard            : contenedor lógico del wizard
        - data-preselected-ids          : JSON con IDs de emisiones
        - data-serie-preselect / titulo / genero / anio : datos de serie existente
        - data-step / data-stepper / data-step-indicator : control de pasos

Interacción JS (en app.js): initSeriesWizardUI
        - Maneja transición de pasos, habilita botones, construye vista previa.
        - Aplica regex para limpiar sufijo '(OBRA GENERAL)' al editar.

Estilos inline: sólo ajustes específicos (scrollbar fina y stacking para typeahead).
================================================================================
--}}
<div id="modal-series-wizard" class="modal-component fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" data-modal-close></div>
        <div class="relative bg-white w-full max-w-4xl sm:max-w-3xl min-h-[50vh] max-h-[85vh] mx-auto mt-8 rounded-xl shadow-2xl flex flex-col transition-all">
                <div class="p-5 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ isset($serie) ? 'Editar serie — Wizard' : 'Crear serie — Wizard' }}</h3>
                        <button type="button" class="text-gray-500" data-modal-close aria-label="Cerrar">✕</button>
                </div>
                <div class="series-wizard-modal-body px-6 pt-4 pb-10 flex-1 overflow-y-auto" id="series-wizard-body" data-series-wizard data-preselected-ids='@json($emisionIds ?? [])' @if(isset($serie)) data-serie-preselect="{{ $serie->NMObra }}" data-serie-titulo="{{ $serie->TituloObra }}" data-serie-genero="{{ $serie->Genero }}" data-serie-anio="{{ $serie->AnioProduccion }}" @endif>
                        <!-- Stepper visual de pasos -->
                        <div class="flex items-center gap-3 mb-6 text-sm" id="wizard-stepper" data-stepper>
                                <div class="flex items-center gap-2" id="wizard-step1-indicator" data-step-indicator="1">
                                        <span class="h-6 w-6 rounded-full flex items-center justify-center text-xs bg-blue-600 text-white" data-step-badge>1</span>
                                        <span class="font-medium" data-step-label>Asignación de obra general</span>
                                </div>
                                <div class="h-px flex-1 bg-gray-200"></div>
                                <div class="flex items-center gap-2 opacity-50" id="wizard-step2-indicator" data-step-indicator="2">
                                        <span class="h-6 w-6 rounded-full flex items-center justify-center text-xs bg-gray-200 text-gray-600" data-step-badge>2</span>
                                        <span data-step-label>Capítulos</span>
                                </div>
                        </div>
                        <!-- Paso 1: Selección / creación de obra general -->
                        <form id="wizard-step1" class="space-y-4" data-step="1">
                                <div class="hidden">
                                        @foreach(($emisionIds ?? []) as $id)
                                                <input type="hidden" name="emision_ids[]" value="{{ $id }}" />
                                        @endforeach
                                </div>
                                <div id="buscar-existente">
                                        <div class="flex items-center justify-between mb-2 gap-4">
                                                <label for="serie-search" class="block text-sm font-medium text-gray-700">Buscar obra general (serie):</label>
                                                <button type="button" id="btn-wizard-crear-obra" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Crear nueva obra</button>
                                        </div>
                                        <div class="relative mb-2" data-obra-typeahead data-typeahead-url="{{ route('obras.search', [], false) }}">
                                                <input type="text" id="serie-search" autocomplete="off" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Escribe para buscar una obra existente..." value="{{ isset($serie) ? $serie->TituloObra : '' }}" />
                                                <div class="absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-72 overflow-y-auto hidden"></div>
                                        </div>
                                        <div id="serie-seleccion" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg {{ isset($serie) ? '' : 'hidden' }}">
                                                <div class="flex items-center justify-between">
                                                        <div>
                                                                <div class="text-sm text-blue-900" id="serie-sel-titulo">{{ isset($serie) ? $serie->TituloObra : '-' }}</div>
                                                                <div class="text-xs text-blue-700" id="serie-sel-detalles">{{ isset($serie) ? (($serie->Genero ? $serie->Genero.' · ' : '') . ($serie->AnioProduccion ?? '')) : '-' }}</div>
                                                        </div>
                                                        <button type="button" id="serie-sel-clear" class="text-xs px-2 py-1 bg-blue-100 rounded">Quitar</button>
                                                </div>
                                                <!-- (Panel de capítulos existentes eliminado por requerimiento actual) -->
                                        </div>
                                        <input type="hidden" name="serie_id" id="serie-id" value="{{ $serie->NMObra ?? '' }}" />
                                </div>
                        </form>
                        <!-- Paso 2: Configuración dinámica de capítulos -->
                        <div id="wizard-step2" class="hidden" data-step="2">
                                <div class="mb-5">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Configurar nombres de capítulos</h4>
                                        <p class="text-xs text-gray-500">Edita el nombre base y el sufijo a aplicar para cada emisión seleccionada.</p>
                                </div>
                                
                                <div class="mb-4 flex items-center gap-3">
                                        <label class="text-sm text-gray-600">Nombre base:</label>
                                        <input type="text" id="wizard-base-titulo" class="flex-1 border rounded px-3 py-2 text-sm" placeholder="Título base de la serie" value="{{ isset($serie) ? preg_replace('/\s*(\(OBRA GENERAL\))?$/i','',$serie->TituloObra) : '' }}" />
                                </div>
                                <div class="mb-6">
                                        <span class="block text-sm font-medium text-gray-700 mb-2">Sufijo para capítulos:</span>
                                        <div class="flex flex-wrap items-center gap-6 text-sm" id="wizard-sufijo-options">
                                                <label class="inline-flex items-center gap-1"><input type="radio" name="wizard-sufijo" value="fecha" checked /> <span>Fecha de emisión</span></label>
                                                <label class="inline-flex items-center gap-1"><input type="radio" name="wizard-sufijo" value="ep" /> <span>EP + correlativo</span></label>
                                                <label class="inline-flex items-center gap-1"><input type="radio" name="wizard-sufijo" value="vacio" /> <span>Sin sufijo</span></label>
                                        </div>
                                </div>
                                <div class="mb-4">
                                        <h5 class="text-xs font-semibold text-gray-600 mb-2">Vista previa</h5>
                                        <ul id="wizard-preview" class="text-sm text-gray-800 space-y-1 bg-gray-50 border rounded p-3 overflow-y-auto pr-2 custom-thin-scroll" style="max-height: 240px; min-height: 40px;"></ul>
                                </div>
                                <!-- Guardado implícito: no requiere checkbox de confirmación -->
                        </div>
                </div>
                <div class="p-4 border-t flex justify-end gap-2 sticky bottom-0 bg-white" id="wizard-step1-actions">
                        <button type="button" class="px-4 py-2 border rounded hover:bg-gray-50" data-modal-close id="wizard-cancel">Cancelar</button>
                        <button type="button" id="wizard-continue" class="px-4 py-2 bg-blue-600 text-white rounded shadow hover:bg-blue-700 disabled:opacity-50" disabled>Continuar</button>
                </div>
                <div class="p-4 border-t flex justify-end gap-2 hidden sticky bottom-0 bg-white" id="wizard-step2-actions">
                        <button type="button" id="wizard-back" class="px-4 py-2 text-sm border rounded hover:bg-gray-50">Volver</button>
                        <button type="button" id="wizard-finish" class="px-5 py-2 bg-green-600 text-white rounded shadow hover:bg-green-700 disabled:opacity-50" disabled>Confirmar y crear capítulos</button>
                </div>
        </div>
</div>

{{-- El modal global de obras se incluye a nivel de layout (no replicar aquí) --}}

<style>
.series-wizard-modal-body { max-height: none !important; overflow-y: visible !important; padding-right: 8px; }
#modal-series-wizard .relative[data-obra-typeahead] { z-index: 60; }
#modal-series-wizard .absolute.z-10 { z-index: 70 !important; }
/* Scrollbar del listado de vista previa */
.custom-thin-scroll::-webkit-scrollbar { width: 6px; }
.custom-thin-scroll::-webkit-scrollbar-track { background: transparent; }
.custom-thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.custom-thin-scroll:hover::-webkit-scrollbar-thumb { background: #94a3b8; }
</style>

<!-- Lógica de interacción en app.js (initSeriesWizardUI) -->

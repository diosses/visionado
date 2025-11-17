{{--
================================================================================
COMPONENTE: modal-identificar-obra.blade.php
--------------------------------------------------------------------------------
Propósito: Asociar (o cambiar) la obra vinculada a una emisión específica.

Funciones principales:
    - Mostrar datos básicos de la emisión (título, canal, fecha, horario) para contexto.
    - Buscar obra existente mediante typeahead (data-obra-typeahead + endpoint obras.search).
    - Previsualizar información de la obra seleccionada y permitir limpiarla.
    - Mostrar obra actualmente asignada (si la hay) con opción de quitar o editar.
    - Acceso directo al wizard de series (btn-open-wizard-serie) para crear obra general.
    - (Si aplica) listar capítulos existentes de una serie y permitir seleccionar uno.

Data attributes clave:
    - data-close-modal         : cierre del modal.
    - data-obra-typeahead      : inicializa componente JS de autocompletado.
    - data-obra-selected       : wrapper de la obra actualmente elegida en el formulario.
    - data-obra-clear          : botón para limpiar selección.
    - data-obra-id             : bloque que refleja obra ya asignada a la emisión.

Flujo esperado (JS externo):
    1. Al abrir: se inyectan datos de la emisión (título y meta) y obra actual (si existe).
    2. Al tipear: peticiones AJAX devuelven sugerencias y se muestran en dropdown.
    3. Al seleccionar obra: se rellena panel "selected-obra-info" y habilita botón Asignar.
    4. Unassign (Quitar obra) limpia la relación actual.
    5. Submit: POST desde JS con la obra elegida para persistir.

Accesibilidad y UX:
    - Estados disabled hasta que exista selección válida.
    - Fallbacks "-" en campos vacíos.

Extensiones posibles:
    - Validación inline de conflicto (por ejemplo, obra ya usada en otra serie exclusiva).
    - Botón para crear capítulo directamente si la obra es serie.
================================================================================
--}}
<div id="modal-identificar-obra" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center min-h-screen w-full hidden z-50">
    <div class="relative mx-auto p-8 border w-full max-w-xl shadow-xl rounded-xl bg-white">
    <div class="mt-1">
            <!-- Header modal -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl text-gray-900">Identificar obra</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-close-modal>
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <!-- Botón acceso al wizard de series -->
            <div class="mb-4" id="identificar-crear-serie-wrap">
                <button type="button" id="btn-open-wizard-serie" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded shadow hover:bg-indigo-700">Crear Serie</button>
            </div>

            <!-- Información contextual de la emisión -->
            <div class="mb-5 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-medium text-sm text-gray-700 mb-2">Emisión:</h4>
                <div class="flex items-start">
                    <p class="flex-1 text-base text-gray-900 break-words whitespace-pre-line leading-snug" id="modal-emision-titulo">-</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs text-gray-500 mt-1" id="modal-emision-meta">
                    <!-- Canal, fecha, horario -->
                </div>
            </div>
            </div>

            

            <!-- Obra actualmente asignada (si existe) -->
            <div id="current-obra" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg hidden" data-obra-id>
                <h4 class="font-medium text-sm text-green-800 mb-1">Obra actualmente asignada:</h4>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-900" id="current-obra-titulo">-</p>
                        <p class="text-xs text-green-700" id="current-obra-detalles">-</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="btn-unassign-obra" class="px-3 py-1 text-xs bg-white text-green-700 border border-green-600 rounded hover:bg-green-100">
                            Quitar obra
                        </button>
                        <button type="button" id="btn-edit-obra" class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">
                            Editar obra
                        </button>
                    </div>
                </div>
            </div>

            <!-- Formulario de búsqueda y asignación -->
            <form id="form-identificar-obra">
                <div class="mb-6">
                    <label for="obra-search" class="block text-sm font-medium text-gray-700 mb-2">
                        Buscar obra:
                    </label>
                    <div class="relative" data-obra-typeahead data-typeahead-url="{{ route('obras.search', [], false) }}">
                        <input 
                            type="text" 
                            id="obra-search" 
                            name="obra_search"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-base"
                            placeholder="Escribe para buscar obras existentes..."
                            autocomplete="off"
                        />
                        <!-- Dropdown dinámico del typeahead -->
                        <div class="absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden"></div>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">
                        Selecciona una obra existente del dropdown o escribe el título exacto
                    </div>
                </div>

                <!-- Panel: obra seleccionada en esta interacción -->
                <div id="selected-obra-info" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg hidden" data-obra-selected>
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium text-sm text-blue-800 mb-1" id="selected-obra-header">Obra seleccionada:</h4>
                            <p class="text-sm text-blue-900" id="selected-obra-titulo" data-obra-selected-label>-</p>
                            <p class="text-xs text-blue-600" id="selected-obra-detalles">-</p>
                            <p class="mt-2 text-xs text-red-600 hidden" id="obra-selection-warning"></p>
                        </div>
                        <button type="button" id="btn-quitar-obra" class="ml-4 px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200" data-obra-clear>Quitar selección</button>
                    </div>
                    <!-- Acción inline para añadir a serie (si corresponde) -->
                    <div id="add-to-serie-inline-wrap" class="mt-2 hidden">
                        <button type="button" id="btn-add-to-serie-inline" class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Añadir a Serie</button>
                    </div>
                    <!-- Panel capítulos (solo visible si la obra es una serie) -->
                    <div id="obra-capitulos-panel" class="mt-3 hidden">
                        <label class="text-xs font-semibold text-blue-700 block mb-1">Capítulos existentes:</label>
                        <div class="flex items-center gap-2">
                            <select id="obra-capitulos-select" class="flex-1 border rounded px-2 py-1 text-xs"></select>
                        </div>
                        <!-- Button-only UX for creating a new chapter is injected by JS next to the select -->
                    </div>
                </div>

                <!-- Acciones finales -->
                <div class="mt-6 space-y-4">
                    <div class="flex flex-row items-center justify-between gap-3">
                    <button type="button" id="btn-create-obra" class="px-4 py-2 text-base text-blue-700 bg-blue-50 border border-blue-300 rounded-lg hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        Crear nueva obra
                    </button>
                    <div class="flex gap-2">
                        <button type="button" class="px-4 py-2 text-base text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition" data-close-modal>
                            Cancelar
                        </button>
                        <button type="submit" id="btn-asignar-obra" class="px-4 py-2 text-base text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" disabled>
                            Asignar obra
                        </button>
                    </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


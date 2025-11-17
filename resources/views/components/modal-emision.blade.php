{{--
================================================================================
COMPONENTE: modal-emision.blade.php
--------------------------------------------------------------------------------
Propósito: Crear o editar una Emisión (registro individual con canal, fecha,
                     horario y vínculo opcional a una obra o capítulo específico).

Características:
    - Soporta modo creación (sin id) y edición (con hidden id).
    - Typeahead para buscar obra y opcionalmente mostrar selector de capítulos si
        la obra es una serie con hijos.
    - Campos horarios con step=1 para permitir segundos si se usan.

Interacción JS (módulo modalEmision.js esperado):
    - Autocompletado de obra (data-obra-typeahead).
    - Poblar panel de capítulos y manejar selección (rellena hidden obra_id si es capítulo).
    - Cargar datos existentes cuando se abre en modo edición.

Notas:
    - Lista de canales se obtiene aquí (query simple) — posible optimización:
        precargar en controlador y pasar colección para evitar query en Blade.
    - Validaciones adicionales (duración consistente inicio/fin) deberían manejarse
        en backend y opcionalmente reflejarse en UI.
================================================================================
--}}
<div id="modal-emision" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
    <button type="button" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700" data-hide="#modal-emision">&times;</button>
        <h2 class="text-lg font-semibold mb-4" id="modal-emision-title">Crear/Editar Emisión</h2>
        <form id="form-emision" autocomplete="off">
            <input type="hidden" name="id" id="emision-id" />
            <div class="mb-3">
                <label for="emision-titulo" class="block text-sm font-medium text-gray-700">Título</label>
                <input type="text" id="emision-titulo" name="TituloEmision" class="mt-1 block w-full border rounded px-3 py-2" required />
            </div>
            <div class="mb-3">
                <label for="emision-canal" class="block text-sm font-medium text-gray-700">Canal</label>
                @php($__canales = \App\Models\Canal::orderBy('nombre')->get())
                <select id="emision-canal" name="canal_id" class="mt-1 block w-full border rounded px-3 py-2">
                    <option value="">Seleccione un canal…</option>
                    @foreach($__canales as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label for="emision-fecha" class="block text-sm font-medium text-gray-700">Fecha emisión</label>
                <input type="date" id="emision-fecha" name="fecha_emision" class="mt-1 block w-full border rounded px-3 py-2" />
            </div>
            <div class="mb-3 flex gap-2">
                <div class="flex-1">
                    <label for="emision-hora-inicio" class="block text-sm font-medium text-gray-700">Hora inicio</label>
                    <input type="time" id="emision-hora-inicio" name="hora_inicio" class="mt-1 block w-full border rounded px-3 py-2" step="1" />
                </div>
                <div class="flex-1">
                    <label for="emision-hora-fin" class="block text-sm font-medium text-gray-700">Hora fin</label>
                    <input type="time" id="emision-hora-fin" name="hora_fin" class="mt-1 block w-full border rounded px-3 py-2" step="1" />
                </div>
            </div>
            <div class="mb-3">
                <label for="emision-obra-search" class="block text-sm font-medium text-gray-700">Obra asignada</label>
                <div class="relative" data-obra-typeahead data-typeahead-url="{{ route('obras.search', [], false) }}">
                    <input 
                        type="text" 
                        id="emision-obra-search" 
                        name="obra_search"
                        class="mt-1 block w-full border rounded px-3 py-2"
                        placeholder="Buscar obra..."
                        autocomplete="off"
                    />
                    <div class="absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden"></div>
                </div>
                <input type="hidden" id="emision-obra-id" name="obra_id" />
                <!-- Panel de capítulos (se muestra dinámicamente si la obra tiene hijos) -->
                <div id="emision-obra-capitulos-panel" class="mt-3 hidden">
                    <label for="emision-obra-capitulos-select" class="block text-sm font-medium text-gray-700">Capítulo</label>
                    <select id="emision-obra-capitulos-select" class="mt-1 block w-full border rounded px-3 py-2"></select>
                    <p class="text-xs text-gray-500 mt-1">Si seleccionas un capítulo, se usará ese ID al guardar la emisión.</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50" data-hide="#modal-emision">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</div>

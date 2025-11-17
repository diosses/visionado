@php
/**
 * Component: modal-obras
 * Params:
 *  - id (string) required: DOM id for the modal (e.g. 'modal-edit-obra-123' or 'modal-create-obra')
 *  - obra (object|null) optional: when present the modal is in "edit" mode and fields are prefilled
 */
// Force create mode if the provided id is a create-id, regardless of any inherited $obra
if (isset($id) && is_string($id) && str_starts_with($id, 'modal-create-obra')) { $obra = null; }
$id = $id ?? ($obra?->NMObra ? 'modal-edit-obra-'.$obra->NMObra : 'modal-create-obra');
$isCreate = empty($obra);
@endphp

<div id="{{ $id }}" class="modal-component fixed inset-0 z-[70] hidden flex items-start sm:items-center justify-center p-4" data-modal-dynamic aria-hidden="true" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black bg-opacity-50" data-modal-close></div>
    <div class="relative bg-white w-full max-w-2xl rounded-lg shadow-lg max-h-[85vh] overflow-hidden" data-modal-body>
        <div class="p-6 border-b flex items-center justify-between sticky top-0 bg-white z-10">
            <h3 class="text-lg font-semibold">{{ $isCreate ? 'Crear nueva obra' : ('Editar obra — '.($obra->TituloObra ?? '')) }}</h3>
            <button type="button" class="text-gray-500" data-modal-close aria-label="Cerrar">✕</button>
        </div>

        @if($isCreate)
            <form method="POST" action="{{ route('obras.quickStore') }}" data-obra-quick novalidate>
                @csrf
        @else
            <form method="POST" action="{{ route('obras.update', $obra->NMObra) }}" data-obra-edit-form="{{ $obra->NMObra }}" novalidate>
                @csrf
                @method('PUT')
        @endif

                <div class="p-6 overflow-y-auto max-h-[65vh]">
                <div id="advertencia-grupo-emisiones" class="mb-4 hidden bg-yellow-100 border-l-4 border-yellow-400 text-yellow-800 p-3 rounded">
                    <strong>ADVERTENCIA:</strong> Se creará una obra para un grupo de emisiones. Si estas emisiones corresponden a una nueva obra en formato capitulado, se debe realizar el procedimiento de alta a través del botón "Crear Serie".
                </div>
                <!-- Anidar como capítulo y obra general (solo si mostrarAnidar) -->
                <div class="mb-4" id="wrap-anidar-capitulo" style="display:none">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                        <input type="checkbox" id="chk-anidar-capitulo" name="anidar_capitulo" class="h-4 w-4" />
                        Anidar como capítulo
                    </label>
                    <div id="panel-anidar-capitulo" class="pl-6 mt-2 hidden">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Obra general/padre</label>
                        <div class="flex items-center gap-2" id="wrap-obra-padre-busqueda">
                            <div class="flex-1 relative" data-obra-typeahead data-typeahead-url="{{ route('obras.search', [], false) }}">
                                <input type="text" id="input-obra-padre" name="obra_padre" class="w-full border rounded px-2 py-1 text-xs" placeholder="Buscar obra general..." autocomplete="off" />
                                <div class="absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden"></div>
                            </div>
                            <input type="hidden" name="obra_padre_id" id="input-obra-padre-id" />
                        </div>
                        <label class="flex items-center gap-2 text-xs mt-2">
                            <input type="checkbox" id="chk-crear-obra-general" name="crear_obra_general" class="h-4 w-4" />
                            Crear nueva obra general
                        </label>
                        <div id="panel-crear-obra-general" class="mt-2 hidden">
                            <input type="text" id="input-nueva-obra-general" name="nueva_obra_general" class="w-full border rounded px-2 py-1 text-xs" placeholder="Título de la obra general" />
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Título de la obra</label>
                        <input type="text" name="TituloObra" maxlength="129" class="w-full border rounded px-3 py-2" value="" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Género</label>
                        <select name="CodGenero" class="w-full border rounded px-3 py-2" required>
                            <option value="" disabled selected>— Selecciona —</option>
                            @foreach(($generosObra ?? []) as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="Genero" value="" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">País de origen</label>
                        <select name="PaisOrigen" class="w-full border rounded px-3 py-2" required>
                            @php($paises = $paisesCatalogo ?? [])
                            @if(empty($paises))
                                <option value="CL">Chile (CL)</option>
                            @else
                                @foreach($paises as $code => $name)
                                    <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Director</label>
                        <input type="text" name="Director" class="w-full border rounded px-3 py-2" value="" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duración (min)</label>
                        <input type="number" name="Duracion" min="0" class="w-full border rounded px-3 py-2" value="" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Año de producción</label>
                        <input type="number" name="AnioProduccion" min="1900" max="{{ date('Y') + 1 }}" class="w-full border rounded px-3 py-2" value="" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Idioma</label>
                        <select name="Idioma" class="w-full border rounded px-3 py-2">
                            @php($idiomas = $idiomasCatalogo ?? [])
                            @if(empty($idiomas))
                                <option value="ES">ES</option>
                            @else
                                @foreach($idiomas as $code => $name)
                                    <option value="{{ strtoupper($code) }}">{{ $name }} ({{ strtoupper($code) }})</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guionista</label>
                        <input type="text" name="Guionista" class="w-full border rounded px-3 py-2" value="" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de obra</label>
                        <select name="TipoObra" class="w-full border rounded px-3 py-2">
                            <option value="">— Seleccionar —</option>
                            <option value="Actoral">Actoral</option>
                            <option value="Danza">Danza</option>
                            <option value="Doblaje">Doblaje</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ficha de Doblaje</label>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="FichaDoblaje" value="1" class="h-4 w-4" disabled />
                            <span class="text-xs text-gray-500">(placeholder — pronto editable)</span>
                        </div>
                        <input type="hidden" name="FichaDoblaje" value="0" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ficha de Imagen</label>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="FichaImagen" value="1" class="h-4 w-4" disabled />
                            <span class="text-xs text-gray-500">(placeholder — pronto editable)</span>
                        </div>
                        <input type="hidden" name="FichaImagen" value="0" />
                    </div>
                </div>
                </div>

                <div class="p-4 border-t flex justify-between items-center bg-white sticky bottom-0">
                    @if(!$isCreate)
                        <button type="button" class="px-4 py-2 border border-red-600 text-red-600 rounded"
                            data-obra-delete
                            data-obra-id="{{ $obra->NMObra }}"
                            data-obra-title="{{ $obra->TituloObra ?? '' }}"
                            data-obra-related-count="{{ (($obra->capitulos_count ?? 0) + ($obra->emisiones?->count() ?? 0)) }}">
                            Eliminar
                        </button>
                    @endif
                    <div class="flex gap-3 ml-auto">
                        <button type="button" class="px-4 py-2 border rounded" data-modal-close>Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">{{ $isCreate ? 'Crear obra' : 'Guardar' }}</button>
                    </div>
                </div>
            </form>
    </div>
</div>

{{-- JS inline eliminado: la lógica de este modal vive en resources/js/modules/modalObras.js y app.js --}}

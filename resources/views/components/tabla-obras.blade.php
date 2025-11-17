{{--
================================================================================
COMPONENTE: tabla-obras
--------------------------------------------------------------------------------
OBJETIVO
Tabla interactiva para explorar, filtrar y gestionar "obras" (y sus capítulos
cuando la obra actúa como contenedor / serie). Incluye:
 1. Filtros (búsqueda, tipo, género, país, rango de años)
 2. Ordenamiento por columnas (ID, Título, Género, País, Año, Tipo)
 3. Selección masiva (bulk) tanto de obras sueltas como de grupos (serie + capítulos)
 4. Modales dinámicos para ver emisiones relacionadas (obra o capítulo)
 5. Reutilización de un modal global para crear / editar obra (activado por data-*).

IMPORTANTE: Este componente NO altera datos por sí mismo; expone data-attributes
que la capa JS (app.js) escucha para:
    - Abrir/cerrar modales
    - Interceptar enlaces de orden/paginación vía AJAX (si se implementa)
    - Gestionar selección bulk y activar la toolbar inferior

PARÁMETROS ESPERADOS
    - $obras: Collection | LengthAwarePaginator de modelos Obra. Cada Obra puede
                        traer (opcionalmente precargado) relaciones:
                            * capitulos (si es serie / obra padre)
                            * emisiones (si se desean mostrar en modal de detalle simple)
                            * elencos (para el componente <x-ficha-badge />)
                            * counts (capitulos_count) mediante withCount()
    - $emptyMessage (opcional): Texto a mostrar si no existen registros (no se usa
                        directamente aquí, pero se deja documentado para consistencia).
    - Colecciones auxiliares opcionales para filtros: $tiposObra, $generosObra,
                        $paisesObra, $aniosObra.

CONCEPTOS CLAVE DE LA UI
    - Obra Padre (serie): Tiene capitulos_count > 0. Su checkbox actúa como "select all"
        sobre los capítulos (data-bulk-obra-group). Las filas de capítulos se renderizan
        como filas hijas ocultas (clase hidden) y se muestran/ocultan con un botón toggle.
    - Obra Simple: Sin capítulos. Su checkbox es individual (data-bulk-obra).
    - Modal de Emisiones: Se generan modales inline (uno por obra y uno por cada capítulo)
        para listar emisiones relacionadas. Se mantienen ocultos hasta que JS los abre.
    - Crear / Editar Obra: El botón data-crear-obra abre el modal global en modo creación;
        los botones con data-editar-obra indican a JS que cargue datos y cambie el modo.

DATA-ATTRIBUTES PRINCIPALES
    data-component="tabla-obras"        -> Raíz del componente
    data-bulk-actions="obras"          -> Barra fija inferior para acciones masivas
    data-bulk-master="obras"           -> Checkbox maestro general
    data-bulk-obra-group                -> Checkbox que representa grupo (padre + hijos)
    data-bulk-obra                      -> Checkbox individual (obra o capítulo)
    data-toggle-target=".row-obra-XYZ" -> Controla expansión de filas hijas
    data-crear-obra / data-editar-obra  -> Disparadores para el modal reutilizable
    data-ver-emisiones                  -> Disparador para abrir modal de emisiones
    data-ajax-swap / data-ajax-url      -> Señalización para mecanismo AJAX genérico

NOTAS DE MANTENIMIENTO
    - Evitar lógica pesada en Blade: Ideal precargar counts y relaciones en el controlador.
    - Si el dataset crece mucho, considerar paginar y mover los modales a carga diferida
        (lazy) en lugar de generarlos todos inline para reducir peso del DOM.
    - Los iconos de orden muestran ▲ (\u25b2) o ▼ (\u25bc) en texto; se podría migrar a
        SVG para consistencia visual si se requiere.
    - Cualquier refactor de bulk selection debe preservar los data-* actuales para no
        romper scripts existentes.

EXTENSIONES FUTURAS POSIBLES (IDEAS)
    - Exportar selección a CSV / Excel usando endpoints dedicados.
    - Inline editing (doble clic) aprovechando Livewire / Alpine si se adopta.
    - Búsqueda reactiva con debounce en lugar de submit tradicional.

Este bloque de documentación es intencionalmente detallado para aprendizaje interno.
================================================================================
--}}

{{-- Componente principal de la tabla de obras --}}
<div data-component="tabla-obras">
    <!-- Toolbar flotante de acciones bulk para obras -->
    <div data-bulk-actions="obras" class="fixed left-0 right-0 bottom-0 z-40 border-t bg-white shadow-lg px-6 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3 hidden">
        <div class="text-sm font-medium text-gray-700">
            <span data-bulk-counter="obras">0 seleccionados</span>
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            <button type="button" data-bulk-action="export" disabled class="px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50">Exportar</button>
            <button type="button" data-bulk-action="delete" disabled class="px-4 py-2 bg-red-600 text-white rounded disabled:opacity-50">Eliminar</button>
        </div>
    </div>
    {{-- Botón para abrir el modal de creación de nueva obra (usa modal global reutilizado) --}}
    <div class="mb-3 flex justify-end">
        <button type="button"
            class="inline-flex items-center gap-2 px-3 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700"
            data-crear-obra>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm.75 6a.75.75 0 0 0-1.5 0v3h-3a.75.75 0 0 0 0 1.5h3v3a.75.75 0 0 0 1.5 0v-3h3a.75.75 0 0 0 0-1.5h-3v-3Z" clip-rule="evenodd" />
            </svg>
            Nueva obra
        </button>
    </div>
    {{-- Formulario de filtros de búsqueda y orden.
        - Method GET para mantener compatibilidad con paginación y orden.
        - Cada select se llena con colecciones auxiliares opcionales.
        - El input hidden tab=obras asegura que al cambiar filtros se conserva el tab activo.
        - El enlace "Limpiar" resetea los parámetros dejando sólo tab=obras. --}}
    <form method="GET" action="{{ request()->url() }}" class="mb-4 flex flex-col md:flex-row md:items-center gap-3" id="filter-obras">
        <input type="hidden" name="tab" value="obras" />
        <input type="text" name="q" value="{{ request('q','') }}" placeholder="Buscar por título" class="border rounded px-3 py-2 md:flex-1" />
        <select name="tipo" class="border rounded px-3 py-2 md:w-48">
            <option value="">Todos los tipos</option>
            @foreach(($tiposObra ?? []) as $t)
                <option value="{{ $t }}" @selected(request('tipo')==$t)>{{ $t }}</option>
            @endforeach
        </select>
        <select name="genero" class="border rounded px-3 py-2 md:w-48">
            <option value="">Todos los géneros</option>
            @foreach(($generosObra ?? []) as $code => $name)
                <option value="{{ $code }}" @selected(request('genero')==$code)>{{ $name }}</option>
            @endforeach
        </select>
        <select name="pais" class="border rounded px-3 py-2 md:w-48">
            <option value="">Todos los países</option>
            @foreach(($paisesObra ?? []) as $p)
                <option value="{{ $p }}" @selected(request('pais')==$p)>{{ $p }}</option>
            @endforeach
        </select>
        <select name="anio_from" class="border rounded px-3 py-2 md:w-32">
            <option value="">Desde año</option>
            @foreach(($aniosObra ?? []) as $a)
                <option value="{{ $a }}" @selected(request('anio_from')==$a)>{{ $a }}</option>
            @endforeach
        </select>
        <select name="anio_to" class="border rounded px-3 py-2 md:w-32">
            <option value="">Hasta año</option>
            @foreach(($aniosObra ?? []) as $a)
                <option value="{{ $a }}" @selected(request('anio_to')==$a)>{{ $a }}</option>
            @endforeach
        </select>
        <button class="px-3 py-2 bg-blue-600 text-white rounded">Filtrar</button>
        <a href="{{ request()->url() }}?tab=obras" class="px-3 py-2 border rounded">Limpiar</a>
    </form>

    {{-- Tabla principal de obras.
        Notas:
        - $sort y $dir se derivan de la query para construir enlaces de orden.
        - Los íconos de orden se muestran sólo en la columna activa.
        - El ID de la tabla (#obras-table) puede usarse para hooks JS futuros. --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="obras-table">
        @php($sort = request('sort'))
        @php($dir = request('dir','asc'))
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left align-middle">
                    <input type="checkbox" class="rounded" data-bulk-master="obras" title="Seleccionar todo" />
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <a href="?tab=obras&sort=NMObra&dir={{ ($sort==='NMObra' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">ID
                        @isset($sort) @if($sort==='NMObra')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <a href="?tab=obras&sort=TituloObra&dir={{ ($sort==='TituloObra' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Título
                        @isset($sort) @if($sort==='TituloObra')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <a href="?tab=obras&sort=Genero&dir={{ ($sort==='Genero' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Género
                        @isset($sort) @if($sort==='Genero')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <a href="?tab=obras&sort=PaisOrigen&dir={{ ($sort==='PaisOrigen' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">País
                        @isset($sort) @if($sort==='PaisOrigen')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <a href="?tab=obras&sort=AnioProduccion&dir={{ ($sort==='AnioProduccion' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Año
                        @isset($sort) @if($sort==='AnioProduccion')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
                    </a>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <a href="?tab=obras&sort=TipoObra&dir={{ ($sort==='TipoObra' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Tipo
                        @isset($sort) @if($sort==='TipoObra')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
                    </a>
                </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ficha</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
            @foreach ($obras as $obra)
                {{-- Fila de obra individual (obra padre o simple). --}}
                <tr class="hover:bg-gray-50 obra-row" data-nmobra="{{ $obra->NMObra }}">
                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                        @if(($obra->capitulos_count ?? 0) > 0)
                                {{-- Checkbox de grupo: controla la selección de todos los capítulos (data-bulk-obra-group) --}}
                            <input type="checkbox" class="rounded" data-bulk-obra-group value="{{ $obra->NMObra }}" data-group="{{ $obra->NMObra }}" title="Seleccionar capítulos del grupo" />
                        @else
                            {{-- Checkbox individual cuando no hay capítulos (obra sin hijos) --}}
                            <input type="checkbox" class="rounded obra-checkbox" data-bulk-obra value="{{ $obra->NMObra }}" />
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $obra->NMObra }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{-- Botón para expandir capítulos si existen (toggle filas hijas) --}}
                        @if(($obra->capitulos_count ?? 0) > 0)
                            <div class="inline-flex items-center gap-2 mr-2">
                                <button type="button" class="text-gray-600 hover:bg-gray-200 rounded p-1 transition" data-toggle-target=".row-obra-{{ $obra->NMObra }}" aria-label="Expandir/collapse">
                                    <span class="expand-icon" data-icon-expand>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                    </span>
                                    <span class="collapse-icon hidden" data-icon-collapse>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        @endif
                        {{ $obra->TituloObra }}
                        @if(($obra->capitulos_count ?? 0) > 0)
                            <span class="ml-2 text-xs text-gray-400">({{ $obra->capitulos_count }} cap.)</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $obra->Genero }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $obra->PaisOrigen }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $obra->AnioProduccion }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $obra->TipoObra }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-ficha-badge :obra="$obra" variant="accion" includeModal="true" :listaElenco="($obra->elencos ?? collect())" />
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 flex gap-3 items-center">
                        {{-- Botón para editar obra (abre modal global reutilizado en modo edición) --}}
                        <button type="button"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-indigo-600 text-indigo-700 hover:bg-indigo-50"
                            data-ajax-swap
                            data-editar-obra="modal-edit-obra-{{ $obra->NMObra }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0  0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            editar
                        </button>
                        {{-- NOTA: El modal de edición ya no se incluye por fila; se usa un único modal dinámico. --}}
                        {{-- Botón para ver emisiones (abre modal de emisiones correspondiente) --}}
                        <button type="button"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-blue-600 text-blue-700 hover:bg-blue-50"
                            data-ajax-swap
                            data-ajax-url="{{ request()->fullUrlWithQuery(['tab' => 'obras']) }}"
                            data-ajax-target="#tab-content"
                            data-ver-emisiones="modal-emisiones-obra-{{ $obra->NMObra }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            ver emisiones
                        </button>
                    </td>
                </tr>
                @if(($obra->capitulos_count ?? 0) > 0)
                    @foreach(($obra->capitulos ?? collect()) as $cap)
                        <tr class="obra-nested-row row-obra-{{ $obra->NMObra }} hidden bg-gray-50" data-nmobra="{{ $cap->NMObra }}" data-group="{{ $obra->NMObra }}">
                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                <input type="checkbox" class="rounded obra-checkbox" data-bulk-obra value="{{ $cap->NMObra }}" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $cap->NMObra }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span>{{ $cap->TituloObra }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cap->Genero }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cap->PaisOrigen }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cap->AnioProduccion }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cap->TipoObra }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <x-ficha-badge :obra="$cap" variant="accion" includeModal="true" :listaElenco="($cap->elencos ?? collect())" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 flex gap-3 items-center">
                                <button type="button"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded border border-indigo-600 text-indigo-700 hover:bg-indigo-50 text-xs"
                                    data-ajax-swap
                                    data-editar-obra="modal-edit-obra-{{ $cap->NMObra }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                    editar
                                </button>
                                {{-- NOTA: Igual que arriba: modal de edición reutilizado; no se genera inline por capítulo. --}}
                                <button type="button"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded border border-blue-600 text-blue-700 hover:bg-blue-50 text-xs"
                                    data-ajax-swap
                                    data-ajax-url="{{ request()->fullUrlWithQuery(['tab' => 'obras']) }}"
                                    data-ver-emisiones="modal-emisiones-obra-{{ $cap->NMObra }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    emisiones
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
        </table>
    </div>

@php(
    $allObras = collect(
        (isset($obras) && is_object($obras) && method_exists($obras, 'items'))
            ? $obras->items()
            : ($obras ?? [])
    )
)

{{-- Modales de emisiones para cada obra --}}
@foreach($allObras as $obra)
    {{-- Emisiones modal for obra (kept inline) --}}
    <div id="modal-emisiones-obra-{{ $obra->NMObra }}" class="fixed inset-0 z-50 hidden modal-component" data-modal-dynamic>
        <div class="absolute inset-0 bg-black bg-opacity-50" data-modal-close></div>
    <div class="relative bg-white w-full max-w-3xl mx-auto mt-16 rounded-lg shadow-lg" data-modal-body>
            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold">Emisiones — {{ $obra->TituloObra }}</h3>
                <button class="text-gray-500" data-modal-close>✕</button>
            </div>
            <div class="p-6 max-h-[65vh] overflow-y-auto">
                @php($isParent = (($obra->capitulos_count ?? 0) > 0))
                @php(
                    $emis = $isParent
                        ? (collect($obra->capitulos ?? collect())->flatMap(function($cap){ return $cap->emisiones ?? collect(); }))
                        : collect($obra->emisiones ?? [])
                )
                @if($emis->isEmpty())
                    <p class="text-sm text-gray-500">No hay emisiones registradas.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                @if($isParent)
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obra</th>
                                @endif
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Canal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horario</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asignación</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($emis as $em)
                                <tr>
                                    @if($isParent)
                                        <td class="px-4 py-2 text-sm text-gray-700">{{ $em->obra?->TituloObra ?? '\u2014' }}</td>
                                    @endif
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $em->canal?->nombre ?? '\u2014' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ optional($em->fecha_emision)->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ (is_string($em->hora_inicio) ? substr($em->hora_inicio,0,5) : '\u2014') }} - {{ (is_string($em->hora_fin) ? substr($em->hora_fin,0,5) : '\u2014') }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">
                                        @php($asig = ($em->asignaciones ?? collect())->first())
                                        @if($asig)
                                            <span class="inline-flex items-center gap-2">
                                                <span class="px-2 py-0.5 text-xs rounded bg-gray-100">{{ $asig->estado }}</span>
                                                <span class="text-xs text-gray-500">{{ $asig->usuario?->name ?? '\u2014' }}</span>
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">Sin asignar</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endforeach

{{-- Modales de emisiones para cada capítulo de cada obra --}}
@foreach($allObras as $obra)
    @foreach(($obra->capitulos ?? collect()) as $cap)
        {{-- Emisiones modal for capítulo --}}
    <div id="modal-emisiones-obra-{{ $cap->NMObra }}" class="fixed inset-0 z-50 hidden modal-component" data-modal-dynamic>
                <div class="absolute inset-0 bg-black bg-opacity-50" data-modal-close></div>
                <div class="relative bg-white w-full max-w-3xl mx-auto mt-16 rounded-lg shadow-lg" data-modal-body>
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Emisiones — {{ $cap->TituloObra }}</h3>
                        <button class="text-gray-500" data-modal-close>✕</button>
                    </div>
                <div class="p-6 max-h-[65vh] overflow-y-auto">
                    @php($emis2 = $cap->emisiones ?? collect())
                    @if($emis2->isEmpty())
                        <p class="text-sm text-gray-500">No hay emisiones registradas.</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Canal</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horario</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asignación</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($emis2 as $em)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-700">{{ $em->canal?->nombre ?? '\u2014' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">{{ optional($em->fecha_emision)->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">{{ (is_string($em->hora_inicio) ? substr($em->hora_inicio,0,5) : '\u2014') }} - {{ (is_string($em->hora_fin) ? substr($em->hora_fin,0,5) : '\u2014') }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">
                                            @php($asig = ($em->asignaciones ?? collect())->first())
                                            @if($asig)
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="px-2 py-0.5 text-xs rounded bg-gray-100">{{ $asig->estado }}</span>
                                                    <span class="text-xs text-gray-500">{{ $asig->usuario?->name ?? '\u2014' }}</span>
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400">Sin asignar</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
@endforeach

<!-- Formulario oculto para eliminar obras vía JS -->
<form id="obra-delete-form" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>

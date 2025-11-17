{{--
================================================================================
COMPONENTE: visionadora/tabla-visionados-user
--------------------------------------------------------------------------------
OBJETIVO
Renderiza la tabla de visionados asignados a un usuario final ("visionadora").
Se utiliza dentro del dashboard del usuario en diferentes pestañas que filtran
por estado lógico de la asignación (pendiente, en_progreso, completada, auditada).

ENTRADAS (@props)
    - visionados: Collection | LengthAwarePaginator de modelos Asignacion (cada
                                asignación debe tener relación 'emision' precargada y esta, a su vez,
                                la relación 'obra' y 'canal' para evitar N+1).
    - estado: string ('pendiente' | 'en_progreso' | 'completada' | 'auditada').
                        Se usa para:
                            * Ajustar copy vacío
                            * Determinar qué acciones (placeholders) mostrar.

PROCESO
        - Agrupación: se replica el patrón de la tabla admin; si la obra pertenece a una serie
            (obra->NMSerie con relación serie) se agrupa por título de la serie; de lo contrario,
            por el título de la emisión. Un único elemento en un grupo se muestra como fila simple.
        - Filas "padre": muestran conteo, rango de fechas (min–max) y estado agregado (único o "Mixto").
        - Filas "hijas": contienen el detalle por asignación. Toggle de expansión reutiliza el
            manejador genérico data-toggle-target definido en app.js (sin JS adicional aquí).
    - Selección masiva: se reutiliza el módulo JS bulkSelection (`resources/js/modules/bulkSelection.js`).
                * data-bulk-master="visionados" -> checkbox maestro.
                * data-bulk-visionado-group -> checkbox de grupo (marca/ desmarca todas las hijas).
                * data-bulk-visionado -> checkboxes individuales.
    - Columna "Emisión": ahora sólo título de emisión (antes mezclaba obra/emisión para compactar).
    - Columna "Obras": nueva columna añadida, muestra badge simple de estado de identificación
    (verde si hay obra asociada, gris si no). Se mantiene mínima para evitar introducir
    dependencias de sugerencias hasta que se unifique lógica con admin.
    - No se crean scripts nuevos; sólo se siguen las convenciones ya soportadas por bulkSelection.
                - El badge de estado usa match() para mapear clases Tailwind.
                - Acciones: ahora sólo botones de acceso al visor:
                            * Padre (grupo): "Visionar en serie" -> idea: abrir playlist secuencial.
                            * Hija / fila simple: "Visionar" individual.
                    (Estados de workflow posteriores se agregarán más adelante, se evita lógica adicional ahora.)

DATA / DISEÑO VISUAL
    - Colores soft (bg-*-100) para consistencia con tablas admin.
    - text-xs en badges para ahorrar espacio horizontal.
    - Fallback '—' (em dash) para datos faltantes.

PAGINACIÓN
    - Si la colección soporta links() (paginador Laravel), se renderiza debajo.
    - Se preservan parámetros de la query actual con appends().
    - Para AJAX progresivo se podrían interceptar los enlaces fuera de este Blade.

OPTIMIZACIONES FUTURAS (IDEAS)
    - Acciones reales (iniciar / completar) con POST vía fetch + refresco ajaxSwap.
    - Columna de progreso (%) o minutos restantes si modo minutado.
    - Componente reusable <x-badge-estado /> para centralizar colores.
    - Tooltips / popovers para metadata adicional de emisión.
    - Endpoint bulk (start/complete) aprovechando set de IDs seleccionados.

PRECONDICIONES RECOMENDADAS EN EL CONTROLADOR
    - with(['emision.obra','emision.canal']) para minimizar queries.
    - Filtrado por estado se aplica antes de pasar la variable a este componente.

Este bloque sirve de guía pedagógica. No altera funcionalidad.
================================================================================
--}}
@props([
        'visionados' => null,   {{-- Colección o paginador de Asignacion --}}
        'estado' => 'pendiente' {{-- Estado contextual de la pestaña activa --}}
])
@php($emptyCopy = [
    'pendiente' => 'No tienes visionados pendientes',
    'en_progreso' => 'No tienes visionados en progreso',
    'completada' => 'No tienes visionados completados',
    // 'auditada' -> Podría añadirse si se habilita pestaña específica
])

<div class="overflow-x-auto" data-component="tabla-visionados-user">
    <!-- Toolbar bulk (se muestra al tener selecciones) -->
    <div data-bulk-actions="visionados" class="fixed left-0 right-0 bottom-0 z-30 border-t bg-white shadow-lg px-6 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3 hidden">
        <div class="text-sm font-medium text-gray-700">
            <span data-bulk-counter="visionados">0 seleccionados</span>
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            <button type="button" data-bulk-action="start" disabled class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50">Iniciar</button>
            <button type="button" data-bulk-action="complete" disabled class="px-4 py-2 bg-green-600 text-white rounded disabled:opacity-50">Marcar completados</button>
        </div>
    </div>
    @php($baseCollection = $visionados instanceof \Illuminate\Pagination\LengthAwarePaginator ? $visionados->getCollection() : collect($visionados))
    @php($grouped = $baseCollection->groupBy(function($asignacion){
        $emi = $asignacion->emision ?? null;
        $obra = $emi?->obra;
        if ($obra && $obra->NMSerie && $obra->serie) {
            return $obra->serie->TituloObra;
        }
        return $emi?->TituloEmision ?: '—';
    }))
    <table class="min-w-full divide-y divide-gray-200" id="visionados-table">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left align-middle">
                    <input type="checkbox" class="rounded" data-bulk-master="visionados" title="Seleccionar todo" />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emisión</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obras</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Canal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha emisión</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($grouped as $tituloGrupo => $lista)
                @if($lista->count() === 1)
                    @php($asignacion = $lista->first())
                    @php($emision = $asignacion->emision)
                    @php($obra = $emision?->obra)
                    <tr class="hover:bg-gray-50 visionado-row" data-asignacion="{{ $asignacion->id }}">
                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                            <input type="checkbox" class="rounded visionado-checkbox" data-bulk-visionado value="{{ $asignacion->id }}" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $emision?->TituloEmision ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php($obraRef = $emision?->obra)
                            @php($emData = [
                                'id' => $emision?->id,
                                'titulo' => $emision?->TituloEmision ?? '',
                                'canal' => $emision?->canal?->nombre ?? '',
                                'fecha' => optional($emision?->fecha_emision)->format('d/m/Y') ?? ''
                            ])
                            <button type="button"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full {{ $obraRef ? 'bg-green-200 text-green-900 border-green-500 tag-obra-asignada' : 'bg-gray-200 text-gray-700' }} hover:opacity-80 transition-opacity"
                                data-identificar-obra
                                data-emision-info='@json($emData)'
                                @if($emision) data-ajax-swap data-ajax-url="{{ route('emisiones.info', ['emision' => $emision->id]) }}" @endif
                                title="Identificar obra">
                                <span class="truncate max-w-[10rem]">{{ $obraRef?->TituloObra ?? 'Obra Desconocida' }}</span>
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $emision?->canal?->nombre ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{-- Fecha y horario (inline) --}}
                            @php($hi = is_string($emision?->hora_inicio) ? substr($emision->hora_inicio,0,5) : null)
                            @php($hf = is_string($emision?->hora_fin) ? substr($emision->hora_fin,0,5) : null)
                            <span class="block truncate max-w-[14rem]">
                                {{ optional($emision?->fecha_emision)->format('d/m/Y') ?? '—' }}
                                @if($hi) {{ ' ' . $hi }} @endif
                                @if($hf) {{ ' - ' . $hf }} @endif
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php($st = $asignacion->estado)
                            <span class="px-2 py-1 text-xs rounded-full {{ match($st){
                                'pendiente' => 'bg-yellow-100 text-yellow-800',
                                'en_progreso' => 'bg-blue-100 text-blue-800',
                                'completada' => 'bg-green-100 text-green-800',
                                'auditada' => 'bg-purple-100 text-purple-800',
                                default => 'bg-gray-100 text-gray-700'
                            } }}">{{ ucfirst(str_replace('_',' ', $st)) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-purple-600 text-white hover:bg-purple-700 text-xs" title="Visionar" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <span>Visionar</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @else
                    @php($groupKey = md5($tituloGrupo ?? ''))
                    @php($firstAsignacion = $lista->first())
                    @php($firstEmision = $firstAsignacion->emision)
                    @php($obra = $firstEmision?->obra)
                    <tr class="hover:bg-gray-50 visionado-row" data-asignacion="{{ $firstAsignacion->id }}">
                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                            <input type="checkbox" class="rounded visionado-checkbox" data-bulk-visionado-group value="{{ $firstAsignacion->id }}" data-group="{{ $groupKey }}" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="flex items-center gap-2">
                                <button type="button" class="text-gray-600 hover:bg-gray-200 rounded p-1 transition" data-toggle-target=".row-vis-{{ $groupKey }}" aria-label="Expandir/collapse">
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
                                <span class="block truncate max-w-[20rem]">{{ $tituloGrupo ?? '—' }}</span>
                            </div>
                            <span class="ml-2 text-xs text-gray-500">&bull; {{ $lista->count() }} asignaciones</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php($obraFirst = $firstEmision?->obra)
                            @php($idsGrupo = $lista->pluck('emision.id')->filter())
                            <button type="button"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full {{ $obraFirst ? 'bg-green-200 text-green-900 border-green-500 tag-obra-asignada' : 'bg-gray-200 text-gray-700' }} hover:opacity-80 transition-opacity"
                                    data-parent-identificar
                                    data-emision-ids="{{ $idsGrupo->implode(',') }}"
                                    data-title="{{ $tituloGrupo }}"
                                    title="Identificar obra (grupo)">
                                <span class="truncate max-w-[10rem]">{{ $obraFirst?->TituloObra ?? 'Obra Desconocida' }}</span>
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $firstEmision?->canal?->nombre ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @php($dates = $lista->pluck('emision.fecha_emision')->filter())
                            @php($minDate = $dates->min())
                            @php($maxDate = $dates->max())
                            @if($minDate && $maxDate && $minDate !== $maxDate)
                                {{ optional($minDate)->format('d/m/Y') }} - {{ optional($maxDate)->format('d/m/Y') }}
                            @else
                                {{ optional($minDate ?: $maxDate)->format('d/m/Y') ?? '\u2014' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php($estados = $lista->pluck('estado')->unique())
                            @if($estados->count() === 1)
                                @php($estadoUnico = $estados->first())
                                <span class="px-2 py-1 text-xs rounded-full {{ match($estadoUnico){
                                    'pendiente' => 'bg-yellow-100 text-yellow-800',
                                    'en_progreso' => 'bg-blue-100 text-blue-800',
                                    'completada' => 'bg-green-100 text-green-800',
                                    'auditada' => 'bg-purple-100 text-purple-800',
                                    default => 'bg-gray-100 text-gray-700'
                                } }}">{{ ucfirst(str_replace('_',' ', $estadoUnico)) }}</span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">Mixto</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-purple-600 text-white hover:bg-purple-700 text-xs" title="Visionar en serie" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <span>Visionar en serie</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @foreach($lista as $asignacion)
                        @php($emision = $asignacion->emision)
                        @php($obra = $emision?->obra)
                        <tr class="visionado-nested-row row-vis-{{ $groupKey }} hidden bg-gray-50" data-asignacion="{{ $asignacion->id }}" data-group="{{ $groupKey }}" data-user-id="{{ $asignacion->user_id }}" data-emision-id="{{ $emision?->id }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <input type="checkbox" class="rounded visionado-checkbox" data-bulk-visionado value="{{ $asignacion->id }}" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $emision?->TituloEmision ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @php($obraRef = $emision?->obra)
                                @php($emData = [
                                    'id' => $emision?->id,
                                    'titulo' => $emision?->TituloEmision ?? '',
                                    'canal' => $emision?->canal?->nombre ?? '',
                                    'fecha' => optional($emision?->fecha_emision)->format('d/m/Y') ?? ''
                                ])
                                <button type="button"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full {{ $obraRef ? 'bg-green-200 text-green-900 border-green-500 tag-obra-asignada' : 'bg-gray-200 text-gray-700' }} hover:opacity-80 transition-opacity"
                                        data-identificar-obra
                                        data-emision-info='@json($emData)'
                                        @if($emision) data-ajax-swap data-ajax-url="{{ route('emisiones.info', ['emision' => $emision->id]) }}" @endif
                                        title="Identificar obra">
                                    <span class="truncate max-w-[10rem]">{{ $obraRef?->TituloObra ?? 'Obra Desconocida' }}</span>
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $emision?->canal?->nombre ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{-- Fecha y horario (inline) --}}
                                @php($hi = is_string($emision?->hora_inicio) ? substr($emision->hora_inicio,0,5) : null)
                                @php($hf = is_string($emision?->hora_fin) ? substr($emision->hora_fin,0,5) : null)
                                <span class="block truncate max-w-[14rem]">
                                    {{ optional($emision?->fecha_emision)->format('d/m/Y') ?? '—' }}
                                    @if($hi) {{ ' ' . $hi }} @endif
                                    @if($hf) {{ ' - ' . $hf }} @endif
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @php($st = $asignacion->estado)
                                <span class="px-2 py-1 text-xs rounded-full {{ match($st){
                                    'pendiente' => 'bg-yellow-100 text-yellow-800',
                                    'en_progreso' => 'bg-blue-100 text-blue-800',
                                    'completada' => 'bg-green-100 text-green-800',
                                    'auditada' => 'bg-purple-100 text-purple-800',
                                    default => 'bg-gray-100 text-gray-700'
                                } }}">{{ ucfirst(str_replace('_',' ', $st)) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-purple-600 text-white hover:bg-purple-700 text-xs" title="Visionar" disabled>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <span>Visionar</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">{{ $emptyCopy[$estado] ?? 'Sin resultados' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if(method_exists($visionados, 'links'))
        <div class="px-4 py-3">{{ $visionados->appends(request()->query())->links() }}</div>
    @endif
</div>
<style>
/* Mantener consistencia visual con admin (borde más marcado en obra asignada) */
.tag-obra-asignada { border-width: 1.5px !important; border-style: solid !important; border-color: #22c55e !important; }
</style>

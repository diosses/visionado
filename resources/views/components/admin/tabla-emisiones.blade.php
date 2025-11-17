{{--
 Componente: admin/tabla-emisiones
 Ámbito: SOLO ADMIN (namespace components/admin)
 Propósito general:
   - Listar emisiones (filas individuales) y agruparlas por título de emisión cuando comparten el mismo texto.
   - Permitir: importar en lote, crear emisión, asignar/reasignar visionadora (individual o grupo), identificar obra (individual o agrupada), editar título inline.
   - Exponer data-* attributes que el JS usa para: selección en lote, apertura de modales, edición inline, identificación de obra y refrescos parciales.
 Notas clave:
   - Mantener estabilidad de los data-* (cambios rompen JS en resources/js/app.js).
   - Evitar onClick inline: todos los eventos se delegan/bindean posteriormente.
   - Cuando se solicita el dashboard con ?ajax=1 este fragmento se inyecta en #tab-content mediante ajaxSwap.
   - Agrupación: si varias emisiones comparten exactamente el mismo TituloEmision se representan como un grupo expandible.
   - Columna "Obra": muestra estado de identificación + sugerencia de similitud (score) si existe y es >= 40.
   - Barra flotante inferior (bulk) aparece sólo si hay selección (controlada por JS con data-bulk-*).
 Dependencias JS (orientativo): bulkSelection, modalObras, modalAsignarEmision, identificar obra, edición de título, refresh de tabs.
--}}
<div class="w-full overflow-x-hidden">
	@php($__fichaObras = [])

	{{-- Barra superior: importación y creación de emisión --}}
	<div class="flex items-center justify-between mb-4">
		<div class="flex items-center gap-2">
			{{-- Importar Emisiones desde archivo XLSX --}}
			<div class="flex items-center gap-3" data-import-emisiones>
				<input type="file" class="hidden" accept=".xlsx" data-import-file />
				<button type="button" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors" data-import-trigger>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
					</svg>
					Importar Emisiones (XLSX)
				</button>
				<div data-import-msg class="text-sm"></div>
			</div>
			<button type="button" class="px-3 py-2 bg-green-600 text-white rounded flex items-center gap-1" data-crear-emision>
				<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
				Crear Emisión
			</button>
		</div>
	</div>

	{{-- Barra flotante inferior para acciones masivas (aparece cuando hay selección) --}}
	<div data-bulk-actions="emisiones" class="fixed left-0 right-0 bottom-0 z-40 border-t bg-white shadow-lg px-6 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3 hidden">
		<div class="text-sm font-medium text-gray-700">
			<span data-bulk-counter="emisiones">0 seleccionados</span>
		</div>
		<div class="flex flex-wrap gap-2 justify-end">
			<button type="button" data-bulk-action="series" disabled class="px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50">Crear Serie</button>
			<button type="button" data-bulk-action="identify" disabled class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50" title="Asignar misma obra a emisiones seleccionadas (película reemitida, etc.)">Identificar obra en lote</button>
			<button type="button" data-bulk-action="open-modal" class="px-4 py-2 bg-green-600 text-white rounded disabled:opacity-50" disabled>Asignar en lote</button>
		</div>
	</div>

	{{-- Filtros de búsqueda básicos --}}
	<form method="GET" action="{{ request()->url() }}" class="mb-4 flex flex-col md:flex-row gap-3 items-center" id="filter-material">
		<input type="hidden" name="tab" value="material" />
		<input type="text" name="q" value="{{ request('q','') }}" placeholder="Buscar por título de obra" class="border rounded px-3 py-2 md:flex-1" />
		<select name="canal" class="border rounded px-3 py-2">
			<option value="">Todos los canales</option>
			@foreach(($canales ?? []) as $c)
				<option value="{{ $c->id }}" @selected(request('canal')==$c->id)>{{ $c->nombre }}</option>
			@endforeach
		</select>
		<input type="date" name="from" value="{{ request('from') }}" class="border rounded px-3 py-2" />
		<input type="date" name="to" value="{{ request('to') }}" class="border rounded px-3 py-2" />
		<button class="px-3 py-2 bg-blue-600 text-white rounded">Filtrar</button>
		<a href="{{ request()->url() }}?tab=material" class="px-3 py-2 border rounded">Limpiar</a>
	</form>
	<table id="emisiones-table" class="w-full table-fixed divide-y divide-gray-200 pb-24"> {{-- Padding inferior para no quedar bajo la barra flotante --}}
		<colgroup>
			<col class="w-[3%]" />
			<col class="w-[20%]" />
			<col class="w-[15%]" />
			<col class="w-[10%]" />
			<col class="w-[15%]" />
			<col class="w-[7%]" />
			<col class="w-[12%]" />
		</colgroup>
		@php($sort = request('sort'))
		@php($dir = request('dir','asc'))
	<thead class="bg-gray-50">
			<tr>
				<th class="px-4 py-3 text-left align-middle">
					<input type="checkbox" class="rounded" data-bulk-master="emisiones" title="Seleccionar todo" />
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título emisión</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obra</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?tab=material&sort=canal&dir={{ ($sort==='canal' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Canal
						@isset($sort) @if($sort==='canal')<span>{{ $dir==='asc' ? '▲':'▼' }}</span>@endif @endisset
					</a>
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?tab=material&sort=fecha_emision&dir={{ ($sort==='fecha_emision' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Fecha emisión
						@isset($sort) @if($sort==='fecha_emision')<span>{{ $dir==='asc' ? '▲':'▼' }}</span>@endif @endisset
					</a>
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ficha</th>
				<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
			</tr>
		</thead>
	<tbody class="bg-white divide-y divide-gray-200" data-bulk="tbody">
			@php($rows = method_exists($emisiones ?? null, 'getCollection') ? $emisiones->getCollection() : (is_iterable($emisiones ?? []) ? collect($emisiones) : collect()))
			@php(
				$grouped = (isset($grupos) && method_exists($grupos, 'items') && isset($byGroup))
					? collect($grupos->items())->mapWithKeys(fn($g) => [ $g->titulo_grupo => ($byGroup[$g->titulo_grupo] ?? collect()) ])
					: ($rows->groupBy(fn($e) => trim((string)($e->TituloEmision ?? '')) !== '' ? trim((string)$e->TituloEmision) : '—'))
			)

		@forelse($grouped as $key => $lista)
			@php($tituloGrupo = trim((string)$key))
			@php($groupKey = md5($tituloGrupo ?? ''))
			@php($obra = optional($lista->first())->obra)
			@php($canal = optional($lista->first())->canal)
			@php($dates = $lista->pluck('fecha_emision')->filter()->map(function($d){
				return $d instanceof \Carbon\Carbon ? $d : ($d ? \Carbon\Carbon::parse($d) : null);
			})->filter())
			@php($minDate = $dates->min())
			@php($maxDate = $dates->max())
			@if($lista->count() === 1)
				@php($emision = $lista->first())
				<tr class="hover:bg-gray-50 emision-row"
					data-nmemision="{{ $emision->id }}"
					data-titulo="{{ $emision->TituloEmision }}"
					data-canal="{{ $emision->canal->nombre ?? '' }}"
					data-fecha="{{ $emision->fecha_emision }}"
					data-hora="{{ $emision->hora_inicio }}">
					<td class="px-4 py-4 whitespace-nowrap text-sm"><input type="checkbox" class="rounded emision-checkbox" data-bulk-emision value="{{ $emision->id }}" /></td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 align-top">
						<div class="inline-flex items-center max-w-[20rem] min-w-0 group">
							<span class="truncate min-w-0" data-emision-titulo-display data-emision-id="{{ $emision->id }}">
								{{ $emision->TituloEmision ?? '—' }}
							</span>
							<button type="button" class="ml-1 opacity-60 group-hover:opacity-100 text-gray-500 hover:text-blue-600 transition" data-edit-emision-title data-emision-id="{{ $emision->id }}" title="Editar título de emisión">
								<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 20h9" />
									<path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
								</svg>
							</button>
						</div>
						<div class="hidden mt-1" data-emision-title-edit="{{ $emision->id }}">
							<div class="flex items-center gap-2">
								<input type="text" class="px-2 py-1 border rounded text-xs flex-1" data-emision-title-input="{{ $emision->id }}" value="{{ $emision->TituloEmision ?? '' }}" />
								<button type="button" class="px-2 py-1 bg-blue-600 text-white text-xs rounded" data-emision-title-save="{{ $emision->id }}">OK</button>
								<button type="button" class="px-2 py-1 border text-xs rounded" data-emision-title-cancel="{{ $emision->id }}">✕</button>
							</div>
						</div>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
						@php($sug = ($suggestions[$emision->id] ?? null))
						@php($score = isset($sug['score']) ? (float)$sug['score'] : null)
						@php($cls = $emision->obra ? 'bg-green-200 text-green-900 border border-green-500' : (
							$score !== null ? (
								$score >= 80 ? 'bg-green-200 text-green-900' : ($score >= 60 ? 'bg-green-100 text-green-800' : ($score >= 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'))
							) : 'bg-gray-200 text-gray-700'
						))
						@php($displayTitle = $emision->obra?->TituloObra ?: (($score !== null && $score >= 40) ? ($sug['TituloObra'] ?? null) : null))
						@php($showScore = (!$emision->obra) && $score !== null && $score >= 40)
						@php($obraDetalles = $emision->obra ? (($emision->obra->AnioIni ?? '') . (($emision->obra->AnioIni && $emision->obra->AnioFin && $emision->obra->AnioIni !== $emision->obra->AnioFin) ? (' - ' . $emision->obra->AnioFin) : '') . ($emision->obra->Genero ? ' | ' . $emision->obra->Genero : '')) : null)
						@php($emisionData = [
							'id' => $emision->id,
							'titulo' => $emision->TituloEmision ?? '',
							'canal' => $emision->canal?->nombre ?? '',
							'fecha' => optional($emision->fecha_emision)->format('d/m/Y') ?? '',
							'sugerencia' => ($score !== null && $score >= 40) ? [
								'obra_id' => $sug['NMObra'] ?? null,
								'titulo' => $sug['TituloObra'] ?? null,
								'score' => $score
							] : null,
							'obraAsignada' => $emision->obra ? [
								'id' => $emision->obra->NMObra,
								'titulo' => $emision->obra->TituloObra ?? '',
								'detalles' => $obraDetalles
							] : null
						])
						<button type="button" class="flex max-w-full min-w-0 items-center gap-1 px-2 py-1 text-xs rounded-full {{ $cls }} hover:opacity-80 transition-opacity" data-identificar-obra data-emision-info='@json($emisionData)' data-ajax-swap data-ajax-url="{{ route('emisiones.info', ['emision' => $emision->id]) }}">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 010 2.828l-9.9 9.9a1 1 0 01-.39.242l-4 1.333a1 1 0 01-1.265-1.265l1.333-4a1 1 0 01.242-.39l9.9-9.9a2 2 0 012.828 0z"/><path d="M15 6l-1-1 2-2 1 1-2 2z"/></svg>
							<span class="flex items-center gap-1 min-w-0">
								<span class="truncate min-w-0">{{ $displayTitle ?? 'Obra Desconocida' }}</span>
								@if($showScore)
									<span class="ml-1 text-xs font-semibold text-gray-500">{{ round($score) }}%</span>
								@endif
							</span>
<style>
.border-green-500 { border-width: 1.5px !important; border-color: #22c55e !important; }
</style>
						</button>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><span class="block truncate max-w-[12rem]">{{ $canal?->nombre ?? '—' }}</span></td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
						<span class="block truncate max-w-[14rem]">
							{{ optional($emision->fecha_emision)->format('d/m/Y') ?? '—' }}
							@if($emision->hora_inicio)
								{{ ' ' . substr($emision->hora_inicio, 0, 5) }}
							@endif
							@if($emision->hora_fin)
								{{ ' - ' . substr($emision->hora_fin, 0, 5) }}
							@endif
						</span>
					</td>
					@php($obraRef = $emision->obra)
					@if($obraRef)
						@php($__fichaObras[$obraRef->NMObra] = $obraRef)
					@endif
					<td class="px-6 py-4 whitespace-nowrap text-sm">
						<x-ficha-badge :obra="$obraRef" variant="estado" />
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-right">
						<button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700" title="Editar emisión" data-editar-emision
							data-emision-id="{{ $emision->id ?? '' }}"
							data-titulo="{{ $emision->TituloEmision ?? '' }}"
							data-canal="{{ $emision->canal->nombre ?? '' }}"
							data-canal-id="{{ $emision->canal_id ?? $emision->canal?->id ?? '' }}"
							data-fecha="{{ $emision->fecha_emision ?? '' }}"
							data-hora-inicio="{{ $emision->hora_inicio ?? '' }}"
							data-hora-fin="{{ $emision->hora_fin ?? '' }}"
							data-obra="{{ $emision->obra?->TituloObra ?? '' }}"
							data-obra-id="{{ $emision->obra_id ?? $emision->obra?->NMObra ?? '' }}">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
							  <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
							</svg>
						</button>
							@php(
								$isAssignedBtn = (int)($emision->asignaciones_count ?? $emision->asignaciones?->count() ?? 0) > 0
							)
							@php($colorClassBtn = $isAssignedBtn ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-600 hover:bg-green-700')
							@php($labelBtn = $isAssignedBtn ? 'Reasignar' : 'Asignar')
							<button type="button"
									class="inline-flex items-center gap-1 px-3 py-1.5 rounded text-white {{ $colorClassBtn }}"
									title="{{ $labelBtn }} a visionadora"
									data-asignar-emision
									data-emision-id="{{ $emision->id }}"
									data-emision-titulo="{{ $emision->TituloEmision }}"
									data-asignado="{{ $isAssignedBtn ? '1' : '0' }}">
								<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2" />
							</svg>
							<span>{{ $labelBtn }}</span>
						</button>
						</td>
					</tr>
			@else
				@php(
					$idsGrupo = $lista->pluck('id')->values()
				)
				@php(
					$visionadoras = $lista->map(function($e){ return optional($e->asignaciones->last())->user_id; })
						->filter()
						->unique()
						->values()
				)
				@php($prefillUserId = $visionadoras->count() === $lista->count() && $visionadoras->count() === 1 ? $visionadoras->first() : null)
				<tr class="hover:bg-gray-50 emision-row" data-nmemision="{{ $lista->first()->id }}">
					<td class="px-4 py-4 whitespace-nowrap text-sm">
						<input type="checkbox" class="rounded emision-checkbox" data-bulk-emision-group value="{{ $lista->first()->id }}" data-group="{{ $groupKey }}" />
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
						<div class="flex items-center gap-2">
							<button type="button" class="text-gray-600 hover:bg-gray-200 rounded p-1 transition" data-toggle-target=".row-emi-{{ $groupKey }}" aria-label="Expandir/collapse">
								<span class="expand-icon" data-icon-expand>
									<!-- Icono expandir -->
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
									  <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
									</svg>
								</span>
								<span class="collapse-icon hidden" data-icon-collapse>
									<!-- Icono colapsar -->
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
									  <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
									</svg>
								</span>
							</button>
							<span class="block truncate max-w-[20rem]">{{ $tituloGrupo ?? '—' }}</span>
						</div>
						<span class="ml-2 text-xs text-gray-500">&bull; {{ $lista->count() }} emisiones</span>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
						@php($firstEm = $lista->first())
						@php($obraFirst = $firstEm?->obra)
						@php($obraSerie = ($obraFirst && $obraFirst->NMSerie) ? $obraFirst->serie : ($obraFirst && !$obraFirst->NMSerie ? $obraFirst : null))
						@php($sug = $firstEm ? ($suggestions[$firstEm->id] ?? null) : null)
						@php($score = isset($sug['score']) ? (float)$sug['score'] : null)
						@php($cls = ($obraSerie || $obra) ? 'bg-green-200 text-green-900 border-green-500 tag-obra-asignada' : (
							$score !== null ? (
								$score >= 80 ? 'bg-green-200 text-green-900' : ($score >= 60 ? 'bg-green-100 text-green-800' : ($score >= 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'))
							) : 'bg-gray-200 text-gray-700'
						))
						@php($displayTitle = $obraSerie?->TituloObra ?: ($obra?->TituloObra ?: (($score !== null && $score >= 40) ? ($sug['TituloObra'] ?? null) : null)))
						@php($serieId = $obraSerie?->NMObra)
						@php($showScore = (!$obraSerie && !$obra) && $score !== null && $score >= 40)
			<button type="button" title="Identificar obra (padre)"
				data-parent-identificar
				data-emision-ids="{{ $lista->pluck('id')->implode(',') }}"
				data-title="{{ $tituloGrupo }}"
				@if($showScore && isset($sug['NMObra'])) data-sugerencia='@json(["obra_id" => $sug["NMObra"], "titulo" => $sug["TituloObra"] ?? ($displayTitle ?? null), "score" => round($score)])' @endif
				@if($serieId) data-serie-id="{{ $serieId }}" @endif
				class="flex max-w-full min-w-0 items-center gap-1 px-2 py-1 text-xs rounded-full {{ $cls }} hover:opacity-80 transition-opacity">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 010 2.828l-9.9 9.9a1 1 0 01-.39.242l-4 1.333a1 1 0 01-1.265-1.265l1.333-4a1 1 0 01.242-.39l9.9-9.9a2 2 0 012.828 0z"/><path d="M15 6l-1-1 2-2 1 1-2 2z"/></svg>
				<span class="flex items-center gap-1 min-w-0">
					<span class="truncate">{{ $displayTitle ?? 'Obra Desconocida' }}</span>
					@if($showScore)
						<span class="ml-1 text-xs font-semibold text-gray-500">{{ round($score) }}%</span>
					@endif
				</span>
			</button>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
						@php($canales = $lista->pluck('canal.nombre')->filter()->unique())
						<span class="block truncate max-w-[12rem]">{{ $canales->count() === 1 ? ($canales->first() ?? '—') : 'Varios' }}</span>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
						<span class="block truncate max-w-[14rem]">
							@if($minDate || $maxDate)
								de {{ optional($minDate)->format('d/m/Y') }} a {{ optional($maxDate)->format('d/m/Y') }}
							@else
								—
							@endif
						</span>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm">
						@php($parentObra = $obraSerie ?: $obra)
						@if($parentObra)
							@php($__fichaObras[$parentObra->NMObra] = $parentObra)
						@endif
						<x-ficha-badge :obra="$parentObra" variant="estado" />
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-sm text-right">
						@php($bulkIdsArray = $idsGrupo->toArray())
						@php($bulkAttr = implode(',', $bulkIdsArray))
						@php($isAssignedGroup = !is_null($prefillUserId))
						@php($colorClassGroup = $isAssignedGroup ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-600 hover:bg-green-700')
						@php($labelGroup = $isAssignedGroup ? 'Reasignar' : 'Asignar')
						<button type="button"
								class="inline-flex items-center gap-1 px-3 py-1.5 rounded text-white {{ $colorClassGroup }}"
								title="{{ $labelGroup }} a visionadora"
								data-asignar-emision
								data-bulk-ids="{{ $bulkAttr }}"
								@if($isAssignedGroup) data-prefill-user="{{ (string)$prefillUserId }}" @endif
								data-group-key="{{ $groupKey }}"
								data-asignado="{{ $isAssignedGroup ? '1' : '0' }}">
							<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2" />
							</svg>
							<span>{{ $labelGroup }}</span>
						</button>
					</td>
				</tr>
				@foreach($lista as $emision)
					<tr class="emision-nested-row row-emi-{{ $groupKey }} hidden bg-gray-50"
						data-nmemision="{{ $emision->id }}"
						data-group="{{ $groupKey }}"
						data-user-id="{{ optional($emision->asignaciones->last())->user_id }}"
						data-titulo="{{ $emision->TituloEmision }}"
						data-canal="{{ $emision->canal->nombre ?? '' }}"
						data-fecha="{{ $emision->fecha_emision }}"
						data-hora="{{ $emision->hora_inicio }}">
						<td class="px-4 py-4 whitespace-nowrap text-sm"><input type="checkbox" class="rounded emision-checkbox" data-bulk-emision value="{{ $emision->id }}" /></td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
							<div class="inline-flex items-center max-w-[16rem] min-w-0 group">
								<span class="truncate min-w-0" data-emision-titulo-display data-emision-id="{{ $emision->id }}">{{ $emision->TituloEmision ?? '—' }}</span>
								<button type="button" class="ml-1 opacity-60 group-hover:opacity-100 text-gray-500 hover:text-blue-600 transition" data-edit-emision-title data-emision-id="{{ $emision->id }}" title="Editar título de emisión">
									<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M12 20h9" />
										<path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
									</svg>
								</button>
							</div>
							<div class="hidden mt-1" data-emision-title-edit="{{ $emision->id }}">
								<div class="flex items-center gap-2">
									<input type="text" class="px-2 py-1 border rounded text-xs flex-1" data-emision-title-input="{{ $emision->id }}" value="{{ $emision->TituloEmision ?? '' }}" />
									<button type="button" class="px-2 py-1 bg-blue-600 text-white text-xs rounded" data-emision-title-save="{{ $emision->id }}">OK</button>
									<button type="button" class="px-2 py-1 border text-xs rounded" data-emision-title-cancel="{{ $emision->id }}">✕</button>
								</div>
							</div>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
							@php($obraRef = $emision->obra)
							@php($emInfo = [
								'id' => $emision->id,
								'titulo' => $emision->TituloEmision ?? '',
								'canal' => $emision->canal->nombre ?? '',
								'fecha' => optional($emision->fecha_emision)->format('d/m/Y') ?? ''
							])
							<button type="button"
									class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full {{ $obraRef ? 'bg-green-200 text-green-900 border-green-500 tag-obra-asignada' : 'bg-gray-200 text-gray-700' }} hover:opacity-80 transition-opacity"
									title="Identificar obra"
									data-identificar-obra
									data-emision-info='@json($emInfo)'
									data-ajax-swap
									data-ajax-url="{{ route('emisiones.info', ['emision' => $emision->id]) }}">
								<span class="truncate">{{ $obraRef?->TituloObra ?? 'Obra Desconocida' }}</span>
							</button>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><span class="block truncate max-w-[12rem]">{{ $emision->canal?->nombre ?? '—' }}</span></td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							<span class="block truncate max-w-[14rem]">
								{{ optional($emision->fecha_emision)->format('d/m/Y') ?? '—' }}
								@if($emision->hora_inicio)
									{{ ' ' . substr($emision->hora_inicio, 0, 5) }}
								@endif
								@if($emision->hora_fin)
									{{ ' - ' . substr($emision->hora_fin, 0, 5) }}
								@endif
							</span>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm">
							@php($parentObra = $obraSerie ?: $obra)
							@if($parentObra)
								@php($__fichaObras[$parentObra->NMObra] = $parentObra)
							@endif
							<x-ficha-badge :obra="$parentObra" variant="estado" />
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-right">
							<button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700" title="Editar emisión" data-editar-emision
								data-ajax-url="{{ route('emisiones.info', ['emision' => $emision->id]) }}"
								data-emision-id="{{ $emision->id }}"
								data-titulo="{{ $emision->TituloEmision ?? '' }}"
								data-canal="{{ $emision->canal->nombre ?? '' }}"
								data-fecha="{{ $emision->fecha_emision ?? '' }}"
								data-hora-inicio="{{ $emision->hora_inicio ?? '' }}"
								data-hora-fin="{{ $emision->hora_fin ?? '' }}"
								data-obra="{{ $emision->obra?->TituloObra ?? '' }}">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
								  <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
							</svg>
							</button>
								@php(
									$isAssignedNested = (int)($emision->asignaciones_count ?? $emision->asignaciones?->count() ?? 0) > 0
								)
								@php($colorClassNested = $isAssignedNested ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-600 hover:bg-green-700')
								@php($labelNested = $isAssignedNested ? 'Reasignar' : 'Asignar')
								<button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded text-white {{ $colorClassNested }}"
										title="{{ $labelNested }} a visionadora"
										data-asignar-emision
										data-emision-id="{{ $emision->id }}"
										data-emision-titulo="{{ $emision->TituloEmision }}"
										data-asignado="{{ $isAssignedNested ? '1' : '0' }}">
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2" />
									</svg>
									<span>{{ $labelNested }}</span>
								</button>
						</td>
					</tr>
				@endforeach
			@endif
		@empty
				<tr>
					<td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">{{ $emptyMessage ?? 'No hay emisiones sin asignar' }}</td>
				</tr>
			@endforelse
		</tbody>
	</table>

	@if(isset($grupos) && method_exists($grupos, 'links'))
		{{ $grupos->appends(request()->query())->links() }}
	@elseif(isset($emisiones) && method_exists($emisiones, 'links'))
		{{ $emisiones->appends(request()->query())->links() }}
	@endif
</div>

{{-- Inclusión de modales globales requeridos por interacciones de la tabla --}}
@include('components.modal-identificar-obra')
@include('components.modal-emision')
@include('components.admin.modal-asignar-emision')
@include('components.series-wizard')

{{-- Modales de ficha de obra para cada obra detectada en esta carga --}}
@foreach($__fichaObras as $__obra)
	@include('components.modal-ficha', ['obra' => $__obra, 'listaElenco' => ($__obra->elencos ?? collect()), 'idPrefix' => 'modal-ficha-obra'])
@endforeach

<style>
.tag-obra-asignada {
	border-width: 2px !important;
	border-style: solid !important;
	border-color: #22c55e !important;
	box-sizing: border-box !important;
	border-radius: 9999px !important;
}
</style>

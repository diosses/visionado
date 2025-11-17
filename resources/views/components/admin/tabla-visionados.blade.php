{{--
 Componente: admin/tabla-visionados
 Ámbito: SOLO ADMIN (namespace components/admin)
 Propósito principal:
	- Mostrar visionados (asignaciones) agrupados por título lógico (serie o título de emisión) en una tabla con columnas ordenables.
    - Permitir reasignación individual o por grupo a una visionadora mediante los <select> (el JS detecta data-* atributos).
    - Exponer data-* attributes para sincronización con otras vistas (por ejemplo, tabla de emisiones) y para acciones en lote.
Parámetros recibidos:
    - $visionados : Paginator|Collection de Asignacion (con relaciones emision, obra, usuario precargadas / eager loaded).
    - $emptyMessage (opcional): Texto personalizado a mostrar cuando no hay registros.
Notas de integración:
    - Este fragmento se inserta dentro de #tab-content cuando el dashboard admin se solicita con ?ajax=1 (vía ajaxSwap).
    - Se evita JS inline (salvo un script mínimo para preseleccionar un <select> de grupo) y se delegan comportamientos a resources/js/app.js.
    - Ordenamiento: los <a href="?tab=visionados&sort=..."> cambian query params; no se hace ordenamiento en el frontend.
    - Agrupación: si la obra pertenece a una serie (NMSerie + relación serie) se agrupa por el título de la serie; si no, por el título de la emisión.
    - Fila simple: grupo de tamaño 1 → se muestra directamente como fila normal.
    - Grupo múltiple: se muestra fila “padre” + filas anidadas ocultas (toggle con botón). Cada fila hija tiene data-group y data-user-id.
    - Estado visual: badge coloreado según estado; si en grupo hay estados distintos se muestra “Mixto”.
	- Reasignación: selects marcados con .visionadora-select; el JS escucha cambios y envía actualizaciones.
	- Selección masiva / grupos: manejada por módulo JS bulkSelection; esta configuración se reusa ahora también
		en la tabla de usuario (visionadora/tabla-visionados-user) evitando duplicar lógica.
    - Paginación: si $visionados es LengthAwarePaginator, se renderiza el paginador estándar de Laravel.
--}}

<div class="overflow-x-auto">
	<table class="min-w-full divide-y divide-gray-200" id="visionados-table">
		@php($sort = request('sort'))
		@php($dir = request('dir','asc'))
		<thead class="bg-gray-50">
			<tr>
					<th class="px-4 py-3 text-left align-middle">
						<input type="checkbox" class="rounded" data-bulk-master="visionados" title="Seleccionar todo" />
					</th>
					<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?tab=visionados&sort=obra&dir={{ ($sort==='obra' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Obra
						@isset($sort) @if($sort==='obra')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
					</a>
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?tab=visionados&sort=canal&dir={{ ($sort==='canal' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Canal
						@isset($sort) @if($sort==='canal')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
					</a>
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?tab=visionados&sort=fecha_emision&dir={{ ($sort==='fecha_emision' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Fecha emisión
						@isset($sort) @if($sort==='fecha_emision')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
					</a>
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?tab=visionados&sort=estado&dir={{ ($sort==='estado' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Estado
						@isset($sort) @if($sort==='estado')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
					</a>
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?tab=visionados&sort=visionadora&dir={{ ($sort==='visionadora' && $dir==='asc') ? 'desc':'asc' }}" class="inline-flex items-center gap-1">Visionadora
						@isset($sort) @if($sort==='visionadora')<span>{{ $dir==='asc' ? '\u25b2':'\u25bc' }}</span>@endif @endisset
					</a>
				</th>
				<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
			</tr>
		</thead>
		<tbody class="bg-white divide-y divide-gray-200">
			@php($collection = $visionados instanceof \Illuminate\Pagination\LengthAwarePaginator ? $visionados->getCollection() : collect($visionados))
			@php($grouped = $collection->groupBy(function($asignacion) {
				// Validación defensiva: si no hay objeto o le falta la relación emision, agrupar bajo guion largo
				if (!is_object($asignacion) || !isset($asignacion->emision)) {
					return '—';
				}
				$emision = $asignacion->emision;
				$obra = $emision?->obra;
				// Si la obra pertenece a una serie, agrupar por el título de la serie
				if ($obra && $obra->NMSerie && $obra->serie) {
					return $obra->serie->TituloObra;
				}
				// Si no, agrupar por el título de la emisión (decisión global de prioridad)
				return $emision?->TituloEmision ?: '—';
			}))
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
							{{ $emision?->TituloEmision ?? '\u2014' }}
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							{{ $emision?->canal?->nombre ?? '\u2014' }}
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							{{-- Fecha y horario (inline previamente en parcial emision-fecha-hora) --}}
							@php($hi = is_string($emision?->hora_inicio) ? substr($emision->hora_inicio,0,5) : null)
							@php($hf = is_string($emision?->hora_fin) ? substr($emision->hora_fin,0,5) : null)
							<span class="block truncate max-w-[14rem]">
								{{ optional($emision?->fecha_emision)->format('d/m/Y') ?? '—' }}
								@if($hi) {{ ' ' . $hi }} @endif
								@if($hf) {{ ' - ' . $hf }} @endif
							</span>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm">
							@php($estado = $asignacion->estado)
							<span class="px-2 py-1 text-xs rounded-full {{ match($estado){
								'pendiente' => 'bg-yellow-100 text-yellow-800',
								'en_progreso' => 'bg-blue-100 text-blue-800',
								'completada' => 'bg-green-100 text-green-800',
								'auditada' => 'bg-purple-100 text-purple-800',
								default => 'bg-gray-100 text-gray-700'
							} }}">{{ ucfirst(str_replace('_',' ', $estado)) }}</span>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							<select class="visionadora-select border rounded px-2 py-1 text-sm" data-asignacion-id="{{ $asignacion->id }}">
								<option value="">—</option>
								@foreach($visionadoras as $visionadora)
									<option value="{{ $visionadora->id }}" @selected($asignacion->user_id == $visionadora->id)>{{ $visionadora->name }}</option>
								@endforeach
							</select>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm">
							<button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-purple-600 text-white hover:bg-purple-700" title="Auditar">
								<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
								</svg>
								<span>Auditar</span>
							</button>
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
							<span class="ml-2 text-xs text-gray-500">&bull; {{ $lista->count() }} asignaciones</span>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							{{ $firstEmision?->canal?->nombre ?? '\u2014' }}
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
								@php($estado = $estados->first())
								<span class="px-2 py-1 text-xs rounded-full {{ match($estado){
									'pendiente' => 'bg-yellow-100 text-yellow-800',
									'en_progreso' => 'bg-blue-100 text-blue-800',
									'completada' => 'bg-green-100 text-green-800',
									'auditada' => 'bg-purple-100 text-purple-800',
									default => 'bg-gray-100 text-gray-700'
								} }}">{{ ucfirst(str_replace('_',' ', $estado)) }}</span>
							@else
								<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">Mixto</span>
							@endif
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							@php($usuarios = $lista->pluck('usuario')->filter()->unique('id'))
							@php($hasUsers = $usuarios->count() > 0)
							@php($multiUsers = $usuarios->count() > 1)
							@php($uniformUserId = (!$multiUsers && $hasUsers) ? optional($usuarios->first())->id : null)
							@php($groupAsignacionIds = $lista->pluck('id')->implode(','))
							@php($groupSelectValue = $multiUsers ? '__varios__' : ($uniformUserId ?? ''))
							<select class="visionadora-select border rounded px-2 py-1 text-sm"
									data-group-select="1"
									data-group-key="{{ $groupKey }}"
									data-group-asignaciones="{{ $groupAsignacionIds }}"
									data-asignacion-id="{{ $firstAsignacion->id }}">
								<option value="">—</option>
								<option value="__varios__" hidden disabled>Varios</option>
								@foreach($visionadoras as $visionadora)
									<option value="{{ $visionadora->id }}" @selected($uniformUserId == $visionadora->id)>{{ $visionadora->name }}</option>
								@endforeach
							</select>
							<script>
								// Pre-selección del <select> de grupo sin mostrar la opción "Varios" explícitamente
								(function(){
									try {
										var sel = document.currentScript && document.currentScript.previousElementSibling;
										if (sel && sel.tagName === 'SELECT') {
											sel.value = @json($groupSelectValue);
										}
									} catch (e) {}
								})();
							</script>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm">
							<button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-purple-600 text-white hover:bg-purple-700" title="Auditar grupo">
								<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
								</svg>
								<span>Auditar</span>
							</button>
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
							<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
								{{ $emision?->canal?->nombre ?? '—' }}
							</td>
							<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
								{{-- Fecha y horario (inline previamente en parcial emision-fecha-hora) --}}
								@php($hi = is_string($emision?->hora_inicio) ? substr($emision->hora_inicio,0,5) : null)
								@php($hf = is_string($emision?->hora_fin) ? substr($emision->hora_fin,0,5) : null)
								<span class="block truncate max-w-[14rem]">
									{{ optional($emision?->fecha_emision)->format('d/m/Y') ?? '—' }}
									@if($hi) {{ ' ' . $hi }} @endif
									@if($hf) {{ ' - ' . $hf }} @endif
								</span>
							</td>
							<td class="px-6 py-4 whitespace-nowrap text-sm">
								@php($estado = $asignacion->estado)
								<span class="px-2 py-1 text-xs rounded-full {{ match($estado){
									'pendiente' => 'bg-yellow-100 text-yellow-800',
									'en_progreso' => 'bg-blue-100 text-blue-800',
									'completada' => 'bg-green-100 text-green-800',
									'auditada' => 'bg-purple-100 text-purple-800',
									default => 'bg-gray-100 text-gray-700'
								} }}">{{ ucfirst(str_replace('_',' ', $estado)) }}</span>
							</td>
							<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
								<select class="visionadora-select border rounded px-2 py-1 text-sm" data-asignacion-id="{{ $asignacion->id }}">
									<option value="">—</option>
									@foreach($visionadoras as $visionadora)
										<option value="{{ $visionadora->id }}" @selected($asignacion->user_id == $visionadora->id)>{{ $visionadora->name }}</option>
									@endforeach
								</select>
							</td>
							<td class="px-6 py-4 whitespace-nowrap text-sm">
								<button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-purple-600 text-white hover:bg-purple-700" title="Auditar">
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
									</svg>
									<span>Auditar</span>
								</button>
							</td>
						</tr>
					@endforeach
				@endif
			@empty
				<tr>
					<td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
						{{ $emptyMessage ?? 'No hay visionados' }}
					</td>
				</tr>
			@endforelse
		</tbody>
	</table>

	@if($visionados instanceof \Illuminate\Pagination\LengthAwarePaginator)
		{{ $visionados->appends(request()->query())->links() }}
	@endif
</div>

{{--
================================================================================
COMPONENTE: admin/modal-asignar-emision.blade.php
--------------------------------------------------------------------------------
Propósito: Asignar una o varias emisiones seleccionadas a una visionadora.

Flujo:
	1. JS abre el modal con lista de emisiones (data-asignar-lista) ya poblada.
	2. Usuario elige visionadora y opcionalmente escribe notas.
	3. Submit envía emision_id (una sola) o emision_ids (bulk) al endpoint asignaciones.

Campos ocultos:
	- emision_id   : Id único cuando se asigna individualmente.
	- emision_ids  : Lista CSV o JSON (dependiendo de JS) para asignación masiva.

Consideraciones:
	- Cargar users (visionadoras) aquí hace query directa; puede delegarse al controlador
		para optimizar (pasar colección ya preparada) si el número crece.
	- Botón submit cambia label mediante data-submit-label si se reutiliza en flujos.

Extensiones futuras:
	- Selector de prioridad o fecha límite.
	- Validaciones preventivas (no reasignar si ya está en progreso, etc.).
================================================================================
--}}
<div id="modal-asignar-emision" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 hidden">
	<div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
		<button type="button" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700" data-hide="#modal-asignar-emision">&times;</button>
		<h2 class="text-lg font-semibold mb-4">Asignar a visionadora</h2>

		<div id="asignar-emision-resumen" class="mb-4 text-sm text-gray-700">
			<div class="font-medium mb-1">Emisiones seleccionadas</div>
			<ul class="list-disc list-inside space-y-1 max-h-32 overflow-auto" data-asignar-lista></ul>
		</div>

		<form id="form-asignar-emision" autocomplete="off">
			<div class="mb-3">
				<label for="asignar-user" class="block text-sm font-medium text-gray-700">Visionadora</label>
				@php($visionadoras = \App\Models\User::orderBy('name')->get())
				<select id="asignar-user" name="user_id" class="mt-1 block w-full border rounded px-3 py-2" required>
					<option value="">Seleccione una visionadora…</option>
					@foreach($visionadoras as $u)
						<option value="{{ $u->id }}">{{ $u->name }}</option>
					@endforeach
				</select>
			</div>

			<div class="mb-4">
				<label for="asignar-notas" class="block text-sm font-medium text-gray-700">Notas (opcional)</label>
				<textarea id="asignar-notas" name="notas" class="mt-1 block w-full border rounded px-3 py-2" rows="2"></textarea>
			</div>

			<input type="hidden" id="asignar-emision-id" name="emision_id" />
			<input type="hidden" id="asignar-emision-ids" name="emision_ids" />

			<div class="mt-6 flex justify-end gap-2">
				<button type="button" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50" data-hide="#modal-asignar-emision">Cancelar</button>
				<button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700" data-submit-label>Asignar</button>
			</div>
		</form>
	</div>
</div>

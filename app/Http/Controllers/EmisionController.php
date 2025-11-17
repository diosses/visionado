<?php

namespace App\Http\Controllers;

use App\Models\Emision;
use App\Models\Obra;
use Illuminate\Http\Request;

/**
 * Controlador de Emisiones: asignación de obra (individual y masiva) y utilidades de serie.
 */
class EmisionController extends Controller
{
    /**
     * Crear o actualizar una emisión (AJAX)
     */
    public function save(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:emisiones,id',
            'TituloEmision' => 'required|string|max:190',
            'canal' => 'nullable|string|max:190',
            'canal_id' => 'nullable|integer|exists:canales,id',
            'fecha_emision' => 'nullable|date',
            'hora_inicio' => 'nullable|string|max:8',
            'hora_fin' => 'nullable|string|max:8',
            'obra_id' => 'nullable|integer|exists:obras,NMObra',
        ]);

        // Resolver canal: preferir canal_id cuando viene del selector. Si no, permitir crear por nombre.
        $canalId = null;
        if (!empty($data['canal_id'])) {
            $canalId = (int) $data['canal_id'];
        } elseif (!empty($data['canal'])) {
            $canalId = \App\Models\Canal::firstOrCreate(['nombre' => $data['canal']])->id;
        }
        $emision = !empty($data['id'])
            ? Emision::findOrFail($data['id'])
            : new Emision();
        $emision->fill([
            'TituloEmision' => $data['TituloEmision'],
            'canal_id' => $canalId,
            'fecha_emision' => $data['fecha_emision'] ?? null,
            'hora_inicio' => $data['hora_inicio'] ?? null,
            'hora_fin' => $data['hora_fin'] ?? null,
            'obra_id' => $data['obra_id'] ?? null,
        ])->save();

        return response()->json([
            'success' => true,
            'emision' => $emision->fresh(['obra','canal'])
        ]);
    }

    public function info(Emision $emision)
    {
        $emision->load(['obra', 'canal']);

        // Cargar asignación actual (si existe). Según modelo de datos, debe haber a lo sumo una por emisión.
        // Usamos fecha_asignacion como referencia; si no existiese, caemos a id desc.
        $asignacion = null;
        try {
            $asignacion = $emision->asignaciones()
                ->when(true, function ($q) {
                    // Evitar error si la columna no existe; si falla, se usará orderBy id
                    try { $q->orderByDesc('fecha_asignacion'); } catch (\Throwable $e) { $q->orderByDesc('id'); }
                })
                ->with('usuario:id,name')
                ->first();
        } catch (\Throwable $e) {
            $asignacion = null;
        }

        // Calcular sugerencia simple por similitud de título (cuando no hay obra asignada)
        $sugerencia = null;
        try {
            if (!$emision->obra) {
                $titulo = trim((string) $emision->TituloEmision);
                if ($titulo !== '') {
                    $needle = $this->norm($titulo);
                    $bestScore = -1; $bestId = null; $bestTitle = null;
                    // Buscar en catálogo de obras por mayor similar_text
                    foreach (\App\Models\Obra::select(['NMObra','TituloObra'])->whereNotNull('TituloObra')->cursor() as $o) {
                        $hay = $this->norm((string) $o->TituloObra);
                        if ($hay === '') { continue; }
                        similar_text($needle, $hay, $pct);
                        if ($pct > $bestScore) { $bestScore = $pct; $bestId = $o->NMObra; $bestTitle = $o->TituloObra; }
                    }
                    if ($bestId) {
                        $sugerencia = [ 'NMObra' => $bestId, 'TituloObra' => $bestTitle, 'score' => round($bestScore, 2) ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silenciar errores de sugerencia; no debe romper el modal
            $sugerencia = null;
        }

        return response()->json([
            'id' => $emision->id,
            'titulo' => $emision->TituloEmision,
            'fecha_emision' => $emision->fecha_emision,
            'hora_inicio' => $emision->hora_inicio,
            'hora_fin' => $emision->hora_fin,
            'canal' => $emision->canal ? [
                'id' => $emision->canal->id,
                'nombre' => $emision->canal->nombre,
            ] : null,
            'obra' => $emision->obra ? [
                'id' => $emision->obra->NMObra,
                'titulo' => $emision->obra->TituloObra,
                'anio_ini' => $emision->obra->AnioIni,
                'anio_fin' => $emision->obra->AnioFin,
                'genero' => $emision->obra->Genero,
            ] : null,
            'asignacion' => $asignacion ? [
                'id' => $asignacion->id,
                'user_id' => $asignacion->user_id,
                'user_name' => optional($asignacion->usuario)->name,
                'estado' => $asignacion->estado ?? null,
                'notas' => $asignacion->notas ?? null,
                'fecha_asignacion' => optional($asignacion->fecha_asignacion)->format('c'),
            ] : null,
            'sugerencia' => $sugerencia,
        ]);
    }

    /**
     * Asignar una obra a una emisión específica
     */
    public function asignarObra(Request $request)
    {
        $data = $request->validate([
            'emision_id' => 'required|integer|exists:emisiones,id',
            'obra_id' => 'required|integer|exists:obras,NMObra',
            'store_original' => 'sometimes|boolean',
            'original_title' => 'sometimes|nullable|string|max:190',
        ]);

    $emision = Emision::findOrFail($data['emision_id']);
    $obra = Obra::findOrFail($data['obra_id']);

    // Solo asociar la obra, nunca renombrar la emisión
    $emision->obra_id = $data['obra_id'];
    $emision->save();
    // Política actual: no se almacena TituloOriginal; se muestra siempre TituloObra sin modificar TituloEmision.

        return response()->json([
            'success' => true,
            'message' => "Obra '{$obra->TituloObra}' asignada correctamente a la emisión",
            'emision_id' => $emision->id,
            'obra_id' => $obra->NMObra,
            'obra_titulo' => $obra->TituloObra,
        ]);
    }

    /**
     * Renombrar título de una emisión.
     */
    public function renombrar(Request $request)
    {
        $data = $request->validate([
            'emision_id' => 'required|integer|exists:emisiones,id',
            'titulo' => 'required|string|max:190'
        ]);
        $emision = Emision::findOrFail($data['emision_id']);
        $emision->TituloEmision = $data['titulo'];
        $emision->save();
        return response()->json(['ok' => true, 'emision_id' => $emision->id, 'titulo' => $emision->TituloEmision]);
    }

    /**
     * Quitar la obra asignada de una emisión (dejar obra_id en null)
     */
    public function quitarObra(Request $request)
    {
        $data = $request->validate([
            'emision_id' => 'required|integer|exists:emisiones,id',
        ]);

        $emision = Emision::findOrFail($data['emision_id']);
        $emision->obra_id = null;
        $emision->save();

        return response()->json([
            'success' => true,
            'message' => 'Obra desasignada correctamente de la emisión',
            'emision_id' => $emision->id,
        ]);
    }

    /**
     * Create a new "Obra General" (series) or assign an existing one, then set NMSerie
     * on all child obras referenced by the provided emission IDs.
     * Request payload: { title: string, obra_id?: int, emision_ids: int[] }
     */
    public function setGeneralForGroup(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:129',
            'obra_id' => 'nullable|integer|exists:obras,NMObra',
            'emision_ids' => 'required|array|min:1',
            'emision_ids.*' => 'integer|exists:emisiones,id',
        ]);

        // Find or create the general obra
        if (empty($data['obra_id'])) {
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '') {
                return response()->json(['ok' => false, 'error' => 'Falta título de la obra general'], 422);
            }
            $general = Obra::create([
                'TituloObra' => $title,
                'Genero' => 'Desconocido',
                'CodGenero' => 'ND',
                'PaisOrigen' => 'ND',
                'TipoObra' => 'Actoral',
            ]);
        } else {
            $general = Obra::findOrFail($data['obra_id']);
        }

        // Gather child obras from emissions and set NMSerie
        $obrasHijas = Emision::whereIn('id', $data['emision_ids'])
            ->whereNotNull('obra_id')
            ->pluck('obra_id')
            ->unique()
            ->values();
        if ($obrasHijas->isNotEmpty()) {
            Obra::whereIn('NMObra', $obrasHijas)->update(['NMSerie' => $general->NMObra]);
        }

        return response()->json([
            'ok' => true,
            'general_id' => $general->NMObra,
            'general_title' => $general->TituloObra,
            'children_count' => $obrasHijas->count(),
        ]);
    }

    // Helper: normaliza string para comparar similitud
    protected function norm(string $s): string
    {
        $s = @iconv('UTF-8','ASCII//TRANSLIT', $s) ?: $s;
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/',' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    }
}

<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Emision;
use App\Models\Obra;
use Illuminate\Support\Facades\DB;

class SeriesWizardApplyController extends Controller
{
    // Devuelve info básica de emisiones para preview
    public function basicInfo(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) return response()->json([]);
        $rows = Emision::whereIn('id', $ids)->get(['id','fecha_emision','TituloEmision','obra_id']);
        return $rows->map(function($e){
            return [
                'id' => $e->id,
                'fecha' => optional($e->fecha_emision)->format('Y-m-d'),
                'fecha_emision' => optional($e->fecha_emision)->format('Y-m-d'),
                'titulo_emision' => $e->TituloEmision,
                'obra_id' => $e->obra_id,
            ];
        });
    }

    // Aplica creación/asignación de capítulos y renombrado
    public function apply(Request $request)
    {
        $data = $request->validate([
            'serie_id' => 'required|integer|exists:obras,NMObra',
            'base' => 'required|string|max:190',
            'sufijo' => 'required|in:fecha,ep,vacio',
            'emisiones' => 'required|array|min:1',
            'emisiones.*.id' => 'required|integer',
            'emisiones.*.nombre' => 'required|string|max:190',
            'keep_originals' => 'sometimes|boolean',
            'originales' => 'sometimes|array',
            'originales.*.id' => 'integer',
            'originales.*.original' => 'nullable|string|max:190',
        ]);

        $serieId = $data['serie_id'];
        $sufijo = $data['sufijo'];
        $base = $data['base'];
        $emisionesInput = collect($data['emisiones']);

        // Preparar nombres deduplicados (ya vienen, pero re-forzamos por seguridad)
        $counts = [];
        $final = $emisionesInput->map(function($it) use (&$counts){
            $n = $it['nombre'];
            $counts[$n] = ($counts[$n] ?? 0) + 1;
            if ($counts[$n] > 1) $n .= ' ('.$counts[$n].')';
            return ['id' => $it['id'], 'nombre' => $n];
        });

        $serie = Obra::findOrFail($serieId);
        // Campos a heredar desde la obra serie hacia cada capítulo.
        // Nota: incluimos TipoObra para cumplir con restricciones NOT NULL y mantener coherencia.
        $defaults = [
            'Genero' => $serie->Genero ?? 'GEN',
            'CodGenero' => $serie->CodGenero ?? 'GEN',
            'PaisOrigen' => $serie->PaisOrigen ?? 'CL',
            'Idioma' => $serie->Idioma ?? 'ES',
            'AnioProduccion' => $serie->AnioProduccion,
            'Director' => $serie->Director,
            'Guionista' => $serie->Guionista,
            'Duracion' => $serie->Duracion,
            'TipoObra' => $serie->TipoObra ?? 'Actoral',
        ];

    try {
    $originalMap = collect($data['originales'] ?? [])->keyBy('id');
    $keepOriginals = (bool)($data['keep_originals'] ?? false);
        DB::transaction(function() use ($final, $serieId, $defaults, $keepOriginals, $originalMap) {
            foreach ($final as $row) {
                $em = Emision::lockForUpdate()->find($row['id']);
                if (!$em) continue;
                $preTituloEmision = $em->TituloEmision; // guardar título previo para posible TituloOriginal

                $obraCap = null;
                $obraActual = $em->obra_id ? Obra::where('NMObra',$em->obra_id)->first() : null;
                $esSerieMisma = $obraActual && $obraActual->NMObra == $serieId; // emission apuntando directamente a la serie, no a un capítulo

                if (!$obraActual || $esSerieMisma) {
                    // Crear capítulo nuevo siempre que la emisión no tenga capítulo propio aún
                    $toCreate = $defaults;
                    $toCreate['TituloObra'] = $row['nombre'];
                    $toCreate['NMSerie'] = $serieId; // enlazar a la serie
                    $obraCap = Obra::create($toCreate);
                    $em->obra_id = $obraCap->NMObra;
                    $em->save();
                } else {
                    // Actualizar capítulo existente (NO tocar si accidentalmente es la serie)
                    if ($obraActual->NMObra != $serieId) {
                        $obraCap = $obraActual;
                        $obraCap->TituloObra = $row['nombre'];
                        if (!$obraCap->NMSerie) $obraCap->NMSerie = $serieId;
                        foreach ($defaults as $k => $v) {
                            if (in_array($k, ['TituloObra','NMSerie'], true)) continue;
                            if (!isset($obraCap->$k) || $obraCap->$k === null || $obraCap->$k === '') {
                                $obraCap->$k = $v;
                            }
                        }
                        $obraCap->save();
                    }
                }

                // Ya no persistimos TituloOriginal: política actual muestra siempre TituloObra sin mutar TituloEmision
            }
        });
        return response()->json(['ok' => true]);
        } catch(\Throwable $e) {
            \Log::error('SeriesWizard apply error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'error' => 'apply_failed', 'message' => $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Elenco;
use App\Models\Obra;
use Illuminate\Http\Request;

class ElencoController extends Controller
{
    public function add($NMObra, Request $request)
    {
        $data = $request->validate([
            'actor_nombre' => 'required|string|max:255',
            'tipo_participacion' => 'nullable|in:Actuación,Danza,Voz',
            'confirmado' => 'nullable|boolean',
        ]);

        // Find or create actor by name (basic heuristic; adapt to your schema)
        $actor = Actor::firstOrCreate([
            'Nombre' => trim($data['actor_nombre']),
        ], [
            'NombreArtistico' => trim($data['actor_nombre']),
        ]);

        Elenco::create([
            'NMObra' => $NMObra,
            'NMActor' => $actor->NMActor,
            'tipo_participacion' => $data['tipo_participacion'] ?? 'Actuación',
            'confirmado' => $request->boolean('confirmado'),
        ]);

        // Optionally mark FichaImagen true if not set
        Obra::where('NMObra', $NMObra)->where(function($q){ $q->whereNull('FichaImagen')->orWhere('FichaImagen', 0); })->update(['FichaImagen' => 1]);

        return back()->with('status', 'Actor agregado al elenco');
    }

    public function save($NMObra, Request $request)
    {
        $validated = $request->validate([
            'NMActor' => 'array',
            'NMActor.*' => 'integer',
            'tipo_participacion' => 'nullable|in:Actuación,Danza,Voz',
            'confirmado' => 'nullable|boolean',
        ]);

        $ids = collect($validated['NMActor'] ?? [])->map(fn($v) => (int)$v)->unique()->values()->all();
        $tipo = $validated['tipo_participacion'] ?? 'Actuación';
        $confirmado = $request->boolean('confirmado');

        $added = 0; $removed = 0;

        \DB::transaction(function() use ($NMObra, $ids, $tipo, $confirmado, &$added, &$removed) {
            // Remove any actor not in the submitted list
            if (count($ids) > 0) {
                $removed = Elenco::where('NMObra', $NMObra)->whereNotIn('NMActor', $ids)->delete();
            } else {
                $removed = Elenco::where('NMObra', $NMObra)->delete();
            }

            // Add any missing actors
            foreach ($ids as $nmActor) {
                $created = Elenco::firstOrCreate([
                    'NMObra' => $NMObra,
                    'NMActor' => $nmActor,
                ], [
                    'tipo_participacion' => $tipo,
                    'confirmado' => $confirmado,
                ]);
                if ($created->wasRecentlyCreated) { $added++; }
            }
        });

        // Ensure ficha flag if there is at least one actor now
        if (count($ids) > 0) {
            Obra::where('NMObra', $NMObra)
                ->where(function($q){ $q->whereNull('FichaImagen')->orWhere('FichaImagen', 0); })
                ->update(['FichaImagen' => 1]);
        }

        // Si la obra es una serie principal (no tiene NMSerie) propagar elenco a sus capítulos
        try {
            $obra = Obra::with('capitulos')->find($NMObra);
            if ($obra && !$obra->NMSerie && $obra->capitulos->count()) {
                $capIds = $obra->capitulos->pluck('NMObra')->all();
                if (count($ids) > 0) {
                    foreach ($capIds as $capId) {
                        // Eliminar actores que ya no están en la lista del padre
                        Elenco::where('NMObra', $capId)->whereNotIn('NMActor', $ids)->delete();
                        // Agregar faltantes
                        foreach ($ids as $nmActor) {
                            Elenco::firstOrCreate([
                                'NMObra' => $capId,
                                'NMActor' => $nmActor,
                            ], [
                                'tipo_participacion' => $tipo,
                                'confirmado' => $confirmado,
                            ]);
                        }
                        // Marcar ficha si corresponde
                        Obra::where('NMObra', $capId)
                            ->where(function($q){ $q->whereNull('FichaImagen')->orWhere('FichaImagen', 0); })
                            ->update(['FichaImagen' => count($ids) > 0 ? 1 : 0]);
                    }
                } else {
                    // Si se limpia elenco del padre, limpiar de capítulos también
                    foreach ($capIds as $capId) {
                        Elenco::where('NMObra', $capId)->delete();
                    }
                }
            }
        } catch(\Throwable $e) {
            \Log::warning('Error replicando elenco a capítulos: '.$e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'added' => $added, 'removed' => $removed]);
        }

        return back()->with('status', 'Elenco actualizado');
    }

    public function remove($NMObra, $NMActor)
    {
        $deleted = Elenco::where('NMObra', $NMObra)->where('NMActor', $NMActor)->delete();
        return back()->with('status', $deleted ? 'Actor eliminado' : 'No se encontró el actor en el elenco');
    }
}

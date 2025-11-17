<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Obra;

class SeriesWizardController extends Controller
{
    // Muestra el modal del wizard; siempre retorna un fragmento HTML para AJAX swap
    public function show(Request $request)
    {
        // IDs de emisiones seleccionadas
        $ids = $request->input('emision_ids', []);
        if (!is_array($ids)) { $ids = []; }

        // Serie ya existente (obra padre) para edición / reutilización
        $serieId = $request->input('serie_id');
        $serie = null;
        if ($serieId) {
            $serie = Obra::find($serieId);
        }
        return view('components.series-wizard', [
            'emisionIds' => $ids,
            'serie' => $serie,
        ]);
    }
}

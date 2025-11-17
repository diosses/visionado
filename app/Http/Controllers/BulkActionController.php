<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Obra;
use App\Models\Emision;
use App\Models\Asignacion;
use App\Models\Visionado;

/**
 * Controlador genérico para acciones masivas disparadas desde bulkSelection.js
 * Endpoint único POST /bulk-actions
 * Payload esperado (JSON): { action: string, context: 'obras'|'emisiones'|'visionados', items: [ids] }
 * Respuesta estándar: { success: bool, message: string, affected?: int, details?: array }
 */
class BulkActionController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|string',
            'context' => 'required|string|in:obras,emisiones,visionados',
            'items' => 'required|array|min:1',
            'items.*' => 'integer'
        ]);

        $action = $data['action'];
        $context = $data['context'];
        $ids = array_unique($data['items']);

        try {
            switch ($action) {
                case 'delete':
                    return $this->bulkDelete($context, $ids);
                case 'export':
                    // Placeholder: la exportación existente probablemente se maneje por otro flujo.
                    return response()->json([
                        'success' => false,
                        'message' => 'Exportación masiva no implementada aún.'
                    ], 400);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Acción no soportada.'
                    ], 400);
            }
        } catch (\Throwable $e) {
            Log::error('[BulkActionController] error', [ 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() ]);
            return response()->json([
                'success' => false,
                'message' => 'Error procesando acción masiva.'
            ], 500);
        }
    }

    protected function bulkDelete(string $context, array $ids)
    {
        $affected = 0; $detail = [];
        DB::transaction(function () use ($context, $ids, &$affected, &$detail) {
            if ($context === 'obras') {
                // Reutilizar lógica de cascada de ObraController sin duplicar código: instanciar y llamar método protegido vía closure
                $obraController = app(\App\Http\Controllers\ObraController::class);
                $ref = new \ReflectionClass($obraController);
                $method = $ref->getMethod('cascadeDeleteObra');
                $method->setAccessible(true);
                foreach ($ids as $id) {
                    $obra = Obra::find($id);
                    if (!$obra) continue;
                    $method->invoke($obraController, $obra);
                    $affected++;
                    $detail[] = [ 'id' => $id ];
                }
            } elseif ($context === 'emisiones') {
                // Borrado simple de emisiones + asignaciones/visionados relacionados
                foreach ($ids as $id) {
                    $em = Emision::find($id);
                    if (!$em) continue;
                    // Eliminar asignaciones y visionados ligados
                    $asig = Asignacion::where('emision_id', $id)->get();
                    foreach ($asig as $a) {
                        Visionado::where('asignacion_id', $a->id)->delete();
                        $a->delete();
                    }
                    $em->delete();
                    $affected++;
                    $detail[] = [ 'id' => $id ];
                }
            } elseif ($context === 'visionados') {
                foreach ($ids as $id) {
                    $vision = Visionado::find($id);
                    if (!$vision) continue;
                    $asig = $vision->asignacion; // puede existir
                    $vision->delete();
                    if ($asig) {
                        // Política: mantener asignación huérfana o eliminarla? Optamos por eliminar si no hay más visionados
                        if (!$asig->visionado) { // relación uno a uno en modelo actual
                            $asig->delete();
                        }
                    }
                    $affected++;
                    $detail[] = [ 'id' => $id ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => $affected ? "{$affected} elemento(s) eliminados." : 'No se eliminó ningún elemento.',
            'affected' => $affected,
            'details' => $detail
        ]);
    }
}

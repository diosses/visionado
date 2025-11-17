<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Models\Asignacion;
use App\Models\Emision;
use App\Models\User;
use App\Models\Canal;
use App\Models\Genero;
use App\Models\Idioma;
use App\Models\Pais;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Controlador del panel de administración.
 *
 * Renderiza las tres pestañas principales:
 * - Visionados: lista de asignaciones y su estado.
 * - Material sin asignar: agrupado por Título Emisión con paginación por grupo.
 * - Obras: catálogo con filtros (tipo, género, país, año).
 */
class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra el panel de administración.
     */
    public function index()
    {
    $tabReq = request('tab');
    $allowedTabs = ['visionados','material','obras'];
    $tab = in_array($tabReq, $allowedTabs, true) ? $tabReq : 'visionados';
        $dir = strtolower(request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

    // Emisiones: mostramos todas las emisiones (asignadas o no), con filtros opcionales

    // Ordenamiento para Obras
        $obrasSort = request('sort', 'TituloObra');
        $obrasAllowed = ['NMObra','TituloObra','Genero','PaisOrigen','AnioProduccion','TipoObra'];
        if (!in_array($obrasSort, $obrasAllowed, true)) { $obrasSort = 'TituloObra'; }

    // Ordenamiento para Emisiones
        $materialSort = request('sort', 'fecha_emision');
        $materialMap = [
            'obra' => 'obras.TituloObra',
            'canal' => 'canales.nombre',
            'fecha_emision' => 'emisiones.fecha_emision',
            'hora_inicio' => 'emisiones.hora_inicio',
            'hora_fin' => 'emisiones.hora_fin',
        ];
        $materialColumn = $materialMap[$materialSort] ?? 'emisiones.fecha_emision';

    // Ordenamiento para Visionados
        $visionadosSort = request('sort', 'fecha_asignacion');
        $visionadosMap = [
            'obra' => 'obras.TituloObra',
            'canal' => 'canales.nombre',
            'fecha_emision' => 'emisiones.fecha_emision',
            'estado' => 'asignaciones.estado',
            'visionadora' => 'users.name',
            'fecha_asignacion' => 'asignaciones.fecha_asignacion',
        ];
        $visionadosColumn = $visionadosMap[$visionadosSort] ?? 'asignaciones.fecha_asignacion';

    // Filtros para Obras
        $qObra = trim((string) request('q'));
        $tipoObra = request('tipo');
        $generoObra = request('genero');
        $paisObra = request('pais');
    // Rangos de año (numéricos)
    $anioFrom = request('anio_from');
    $anioTo = request('anio_to');
    $anioFromYear = is_numeric($anioFrom) ? (int) $anioFrom : null;
    $anioToYear = is_numeric($anioTo) ? (int) $anioTo : null;

    // Obras de nivel superior (series o unitarias): NMSerie null o 0
        $obras = Obra::withCount(['capitulos','elencos'])
            ->with([
                'capitulos',
                'capitulos.emisiones.canal',
                'capitulos.emisiones.asignaciones.usuario',
                'elencos.actor',
                'emisiones.canal',
                'emisiones.asignaciones.usuario'
            ])
            ->where(function($q){ $q->whereNull('NMSerie')->orWhere('NMSerie', 0); })
            ->when($qObra !== '', fn($qb) => $qb->where('TituloObra', 'like', "%$qObra%"))
            ->when($tipoObra, fn($qb) => $qb->where('TipoObra', $tipoObra))
            ->when($generoObra, fn($qb) => $qb->where('CodGenero', $generoObra))
            ->when($paisObra, fn($qb) => $qb->where('PaisOrigen', $paisObra))
            ->when($anioFromYear, fn($qb) => $qb->where('AnioProduccion', '>=', $anioFromYear))
            ->when($anioToYear, fn($qb) => $qb->where('AnioProduccion', '<=', $anioToYear))
            ->orderBy($obrasSort, $dir)
            ->paginate(15);
    // Listas distintas para filtros
        $tiposObra = Obra::select('TipoObra')->whereNotNull('TipoObra')->distinct()->orderBy('TipoObra')->pluck('TipoObra');
        // Catálogo de géneros normalizados
        $generosObra = Genero::orderBy('nombre')->pluck('nombre', 'codigo');
    $paisesObra = Obra::select('PaisOrigen')->whereNotNull('PaisOrigen')->distinct()->orderBy('PaisOrigen')->pluck('PaisOrigen');
    // Catálogos ISO para formularios (selects)
    $idiomasCatalogo = Idioma::orderBy('nombre')->pluck('nombre', 'codigo');
    $paisesCatalogo = Pais::orderBy('nombre')->pluck('nombre', 'codigo');
        $aniosObra = Obra::select('AnioProduccion')
            ->whereNotNull('AnioProduccion')
            ->distinct()
            ->orderBy('AnioProduccion', 'desc')
            ->pluck('AnioProduccion');

    // Estadísticas rápidas
        $totalCompletados = Asignacion::where('estado', 'completada')->count();
        $totalPendientes = Asignacion::where('estado', 'pendiente')->count();
        $totalEnProgreso = Asignacion::where('estado', 'en_progreso')->count();
        $totalPorAuditar = Asignacion::where('estado', 'completada')
            ->whereDoesntHave('visionado.auditoria')
            ->count();

    // Listas para pestañas
    // For visionados, we need to group before pagination to count groups as single items
    $visionadosQuery = Asignacion::query()
            ->select('asignaciones.*')
            ->leftJoin('emisiones', 'emisiones.id', '=', 'asignaciones.emision_id')
            ->leftJoin('obras', 'obras.NMObra', '=', 'emisiones.obra_id')
            ->leftJoin('obras as series', 'series.NMObra', '=', 'obras.NMSerie')
            ->leftJoin('canales', 'canales.id', '=', 'emisiones.canal_id')
            ->leftJoin('users', 'users.id', '=', 'asignaciones.user_id')
            ->with(['emision.obra.serie', 'emision.canal', 'usuario'])
            ->orderBy($visionadosColumn, $dir);

    // Get all visionados and group them
    $allVisionados = $visionadosQuery->get();
    $groupedVisionados = $allVisionados->groupBy(function($asignacion) {
        $emision = $asignacion->emision;
        $obra = $emision?->obra;
        
        // If obra has a parent series (NMSerie), group by the parent serie title
        if ($obra && $obra->NMSerie && $obra->serie) {
            return $obra->serie->TituloObra;
        }
        
        // Otherwise group by obra title or emission title as fallback
        return $obra ? $obra->TituloObra : ($emision?->TituloEmision ?: '—');
    });

    // Paginate the groups, not individual items
    $page = max(1, (int) request('page', 1));
    $perPage = 10;
    $totalGroups = $groupedVisionados->count();
    $groupsSlice = $groupedVisionados->slice(($page-1)*$perPage, $perPage);
    
    // Flatten the slice back to individual asignaciones for the view
    $visionadosForPage = $groupsSlice->flatten();
    
    // Create paginator manually with group count as total
    $visionados = new \Illuminate\Pagination\LengthAwarePaginator(
        $visionadosForPage, 
        $totalGroups, 
        $perPage, 
        $page, 
        ['path' => request()->url(), 'query' => request()->query()]
    );

    // Filtros para Emisiones
        $qMat = trim((string) request('q'));
        $canalId = request('canal');
        $fDesde = request('from');
        $fHasta = request('to');

    $emisiones = Emision::query()
            ->select('emisiones.*')
            ->leftJoin('obras', 'obras.NMObra', '=', 'emisiones.obra_id')
            ->leftJoin('canales', 'canales.id', '=', 'emisiones.canal_id')
        ->with(['obra.elencos.actor', 'canal'])
        ->withCount('asignaciones')
            ->when($qMat !== '', function($qb) use ($qMat){
                $qb->where(function($q) use ($qMat) {
                    $q->where('obras.TituloObra', 'like', "%$qMat%")
                        ->orWhere('emisiones.TituloEmision', 'like', "%$qMat%");
                });
            })
            ->when($canalId, fn($qb) => $qb->where('emisiones.canal_id', $canalId))
            ->when($fDesde, fn($qb) => $qb->whereDate('emisiones.fecha_emision', '>=', $fDesde))
            ->when($fHasta, fn($qb) => $qb->whereDate('emisiones.fecha_emision', '<=', $fHasta))
            ->orderBy($materialColumn, $dir)
            ->paginate(20);

    // Construir agrupaciones por Serie (obra general) si existe; caso contrario por TituloEmision (todas las emisiones)
        $groupBase = Emision::query()
            ->leftJoin('obras as o', 'o.NMObra', '=', 'emisiones.obra_id')
            ->leftJoin('obras as s', 's.NMObra', '=', 'o.NMSerie')
            ->when($qMat !== '', function($qb) use ($qMat){
                $qb->where(function($q) use ($qMat) {
                    $q->where('emisiones.TituloEmision', 'like', "%$qMat%")
                      ->orWhereIn('emisiones.obra_id', function($sub) use($qMat){
                          $sub->select('NMObra')->from('obras')->where('TituloObra', 'like', "%$qMat%");
                      })
                      ->orWhere('s.TituloObra', 'like', "%$qMat%");
                });
            })
            ->when($canalId, fn($qb) => $qb->where('emisiones.canal_id', $canalId))
            ->when($fDesde, fn($qb) => $qb->whereDate('emisiones.fecha_emision', '>=', $fDesde))
            ->when($fHasta, fn($qb) => $qb->whereDate('emisiones.fecha_emision', '<=', $fHasta));

        $groupsAll = $groupBase
            ->clone()
            ->selectRaw('COALESCE(NULLIF(TRIM(s.TituloObra), ""), COALESCE(NULLIF(TRIM(emisiones.TituloEmision), ""), "—")) as titulo_grupo')
            ->selectRaw('MIN(fecha_emision) as min_fecha')
            ->selectRaw('MAX(fecha_emision) as max_fecha')
            ->selectRaw('COUNT(*) as emis_count')
            ->groupBy('titulo_grupo')
            ->orderBy('max_fecha', $dir)
            ->get();

        $page = max(1, (int) request('page', 1));
    $perPage = 20;
        $totalGroups = $groupsAll->count();
        $groupsSlice = $groupsAll->slice(($page-1)*$perPage, $perPage)->values();
        $emisionesGroups = new LengthAwarePaginator($groupsSlice, $totalGroups, $perPage, $page, [ 'path' => request()->url(), 'query' => request()->query() ]);

    // Hijos por grupos seleccionados (todas las emisiones)
        $groupTitles = $groupsSlice->pluck('titulo_grupo')->all();
        $childrenRows = Emision::query()
            ->leftJoin('obras as o', 'o.NMObra', '=', 'emisiones.obra_id')
            ->leftJoin('obras as s', 's.NMObra', '=', 'o.NMSerie')
            ->select('emisiones.*')
            ->with(['obra.elencos.actor', 'obra.serie', 'canal'])
            ->withCount('asignaciones')
            ->when($qMat !== '', function($qb) use ($qMat){
                $qb->where(function($q) use ($qMat) {
                    $q->where('emisiones.TituloEmision', 'like', "%$qMat%")
                      ->orWhereIn('emisiones.obra_id', function($sub) use($qMat){
                          $sub->select('NMObra')->from('obras')->where('TituloObra', 'like', "%$qMat%");
                      })
                      ->orWhere('s.TituloObra', 'like', "%$qMat%");
                });
            })
            ->when($canalId, fn($qb) => $qb->where('emisiones.canal_id', $canalId))
            ->when($fDesde, fn($qb) => $qb->whereDate('emisiones.fecha_emision', '>=', $fDesde))
            ->when($fHasta, fn($qb) => $qb->whereDate('emisiones.fecha_emision', '<=', $fHasta))
            ->orderBy('emisiones.fecha_emision', $dir)
            ->get();
        $childrenList = $childrenRows->groupBy(function($e){
            $serieTitle = optional($e->obra?->serie)->TituloObra;
            if ($serieTitle && trim($serieTitle) !== '') return $serieTitle;
            $te = trim((string)$e->TituloEmision);
            return $te !== '' ? $te : '—';
        });
    // Keep only current page groups to render (cast to base collection to avoid Eloquent-only getKey())
    $emisionesByGroup = $childrenList->toBase()->only($groupTitles);

    // Precálculo de sugerencias por similitud para colorear la UI
        $obrasList = Obra::select(['NMObra','TituloObra'])->whereNotNull('TituloObra')->get();
        $suggestions = [];
    $forSuggest = $childrenList->flatten(1);
        foreach ($forSuggest as $emi) {
            $title = trim((string)$emi->TituloEmision);
            if ($title === '') { $suggestions[$emi->id] = null; continue; }
            $best = null; $score = -1; $bestId = null; $bestTitle = null;
            $needle = $this->norm($title);
            foreach ($obrasList as $o) {
                $hay = $this->norm($o->TituloObra ?? '');
                if ($hay === '') continue;
                similar_text($needle, $hay, $pct);
                if ($pct > $score) { $score = $pct; $best = $o; $bestId = $o->NMObra; $bestTitle = $o->TituloObra; }
            }
            $suggestions[$emi->id] = $best ? ['NMObra' => $bestId, 'TituloObra' => $bestTitle, 'score' => $score] : null;
        }
        $canales = Canal::orderBy('nombre')->get();

    // Visionadoras disponibles para el modal de asignar
    $visionadoras = User::whereHas('role', function ($q) {
        $q->whereRaw('LOWER(name) = ?', ['visionadora']);
        })
        ->orderBy('name')
        ->get();
    if ($visionadoras->isEmpty()) {
        // Fallback: list all users so assignment remains usable if roles are not set up yet
        $visionadoras = User::orderBy('name')->get();
    }

        // Consolidate view data to avoid duplication between full and AJAX responses
        $vars = compact(
            'tab',
            'obras',
            'totalCompletados',
            'totalPendientes',
            'totalEnProgreso',
            'totalPorAuditar',
            'visionados',
            'emisiones',
            'emisionesGroups',
            'emisionesByGroup',
            'suggestions',
            'visionadoras',
            'tiposObra', 'generosObra', 'paisesObra', 'canales', 'aniosObra',
            'idiomasCatalogo', 'paisesCatalogo'
        );

        // If AJAX request (either real XHR or query flag), return only the tab content section for fragment swap
        if (request()->ajax() || request('ajax')) {
            $view = view('dashboard.admin', $vars);
            $sections = $view->renderSections();
            if (isset($sections['tab_content'])) {
                return response($sections['tab_content']);
            }
            // Fallback: return full rendered view (should rarely happen if section defined)
            return response($view->render());
        }

        return view('dashboard.admin', $vars);
    }

    protected function norm(string $s): string
    {
        $s = iconv('UTF-8','ASCII//TRANSLIT', $s);
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/',' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    }
}
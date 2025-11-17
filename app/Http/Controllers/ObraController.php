<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Models\Emision;
use App\Models\Asignacion;
use App\Models\Visionado;
use App\Models\Bloque;
use App\Models\Auditoria;
use App\Models\Elenco;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Genero;

/**
 * Controlador de Obras: listado, alta rápida, búsqueda y borrado en cascada.
 */
class ObraController extends Controller
{
    // Normaliza campos comunes para store, update y quickStore
    protected function normalizeObraFields(array $data): array
    {
        $data['FichaDoblaje'] = (int)($data['FichaDoblaje'] ?? 0);
        $data['FichaImagen'] = (int)($data['FichaImagen'] ?? 0);
        if (isset($data['Idioma'])) {
            $data['Idioma'] = strtoupper(substr(trim($data['Idioma']), 0, 5));
        }
        $data['PaisOrigen'] = strtoupper(substr(trim($data['PaisOrigen'] ?? 'ND'), 0, 5));
        $data['Genero'] = trim($data['Genero'] ?? '');
        if (!empty($data['CodGenero'])) {
            $nombre = Genero::where('codigo', $data['CodGenero'])->value('nombre');
            if ($nombre) { $data['Genero'] = $nombre; }
        }
        if (empty($data['CodGenero'])) {
            $data['CodGenero'] = $this->guessCodGenero($data['Genero'] ?? '');
        }
        if (empty($data['Genero'])) { $data['Genero'] = 'Desconocido'; }
        return $data;
    }
    /**
     * Muestra una lista de todas las obras.
     */
    public function index()
    {
        // Esta ruta no se usa en el dashboard actual. Evita romper si alguien navega aquí.
        return redirect()->route('dashboard.admin', ['tab' => 'obras']);
    }

    /**
     * Muestra los detalles de una obra específica.
     * Si la petición espera JSON (AJAX/Fetch con Accept: application/json),
     * responde con los campos de la obra en JSON para uso del modal de edición.
     * Caso contrario, mantiene el redirect al dashboard (comportamiento legacy).
     */
    public function show(Request $request, $id)
    {
        // Cuando es una petición AJAX/JSON, devolver los datos de la obra
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson() || $request->query('json') == 1) {
            $obra = Obra::findOrFail($id);
            // Devolver directamente el modelo (Laravel lo serializa a JSON)
            return response()->json($obra);
        }

        // Navegación normal: redirigir al dashboard con la pestaña de obras
        return redirect()->route('dashboard.admin', ['tab' => 'obras']);
    }

    /**
     * Muestra el formulario para crear una nueva obra.
     */
    public function create()
    {
        return redirect()->route('dashboard.admin', ['tab' => 'obras']);
    }

    /**
     * Almacena una nueva obra en la base de datos.
     */
    public function store(Request $request)
    {
    $validated = $request->validate([
            'TituloObra' => 'required|string|max:129',
            'Genero' => 'required|string|max:13',
            'PaisOrigen' => 'required|string|max:5|exists:paises,codigo',
            'Director' => 'nullable|string|max:255',
            'Duracion' => 'nullable|integer|min:0',
            'AnioProduccion' => 'nullable|integer|min:1900|max:'.(date('Y')+1),
            'Idioma' => 'nullable|string|max:5|exists:idiomas,codigo',
            'FichaDoblaje' => 'nullable|boolean',
            'Guionista' => 'nullable|string|max:255',
            'TipoObra' => 'nullable|in:Actoral,Danza,Doblaje',
            'CodGenero' => 'nullable|string|max:5',
            'FichaImagen' => 'nullable|boolean',
        ]);

        $validated = $this->normalizeObraFields($validated);
        Obra::create($validated);

        return redirect()->route('dashboard.admin', ['tab' => 'obras'])
            ->with('success', 'Obra creada exitosamente.');
    }

    /**
     * Muestra el formulario para editar una obra existente.
     */
    public function edit($id)
    {
        return redirect()->route('dashboard.admin', ['tab' => 'obras']);
    }

    /**
     * Actualiza una obra existente en la base de datos.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'TituloObra' => 'required|string|max:129',
            'Genero' => 'required|string|max:13',
            'PaisOrigen' => 'required|string|max:5|exists:paises,codigo',
            'Director' => 'nullable|string|max:255',
            'Duracion' => 'nullable|integer|min:0',
            'AnioProduccion' => 'nullable|integer|min:1900|max:'.(date('Y')+1),
            'Idioma' => 'nullable|string|max:5|exists:idiomas,codigo',
            'FichaDoblaje' => 'nullable|boolean',
            'Guionista' => 'nullable|string|max:255',
            'TipoObra' => 'nullable|in:Actoral,Danza,Doblaje',
            'CodGenero' => 'nullable|string|max:5',
            'FichaImagen' => 'nullable|boolean',
        ]);

        $validated = $this->normalizeObraFields($validated);
        $obra = Obra::findOrFail($id);
        $obra->update($validated);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['ok' => true, 'NMObra' => $obra->NMObra, 'TituloObra' => $obra->TituloObra]);
        }

        return redirect()->route('dashboard.admin', ['tab' => 'obras'])
            ->with('success', 'Obra actualizada exitosamente.');
    }

    /**
     * Elimina una obra de la base de datos.
     */
    public function destroy($id)
    {
        $obra = Obra::findOrFail($id);

        DB::transaction(function () use ($obra) {
            $this->cascadeDeleteObra($obra);
        });

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Obra y datos relacionados eliminados.']);
        }

        return redirect()->route('dashboard.admin', ['tab' => 'obras'])
            ->with('success', 'Obra y datos relacionados eliminados.');
    }

    // Búsqueda incremental de obras por título (para modales de asignación)
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Prioriza coincidencias por prefijo, luego por borde de palabra y finalmente por substring
        $qStart = $q . '%';
        $qWord  = '% ' . $q . '%';
        $qLike  = '%' . $q . '%';
        $orderSql = 'CASE '
            . 'WHEN TituloObra LIKE ? THEN 0 '
            . 'WHEN CONCAT(" ", TituloObra) LIKE ? THEN 1 '
            . 'WHEN TituloObra LIKE ? THEN 2 '
            . 'ELSE 9 END';

        $obras = Obra::query()
            ->select(['NMObra', 'TituloObra', 'AnioProduccion', 'Genero'])
            ->whereNull('NMSerie') // excluir capítulos anidados
            ->where('TituloObra', 'like', $qLike)
            ->orderByRaw($orderSql, [$qStart, $qWord, $qLike])
            ->orderByRaw('COALESCE(AnioProduccion, 0) DESC')
            ->orderBy('TituloObra')
            ->limit(20)
            ->get()
            ->map(function ($o) {
                $count = Obra::where('NMSerie', $o->NMObra)->limit(1)->count();
                return [
                    'NMObra' => $o->NMObra,
                    'TituloObra' => $o->TituloObra,
                    'Genero' => $o->Genero,
                    'AnioProduccion' => $o->AnioProduccion,
                    'label' => trim($o->TituloObra . ($o->AnioProduccion ? " ({$o->AnioProduccion})" : '')),
                    'is_padre' => $count > 0,
                ];
            });

        return response()->json($obras);
    }

    // Alta rápida de obra mínima (desde modal)
    public function quickStore(Request $request)
    {
        // Normalizar entradas vacías a null para evitar fallos de 'integer' en strings vacíos
        foreach (['NMSerie','obra_padre_id'] as $k) {
            if ($request->has($k) && trim((string)$request->input($k)) === '') {
                $request->merge([$k => null]);
            }
        }

        // 1) Validación básica de la obra hija a crear + banderas de anidación
        $data = $request->validate([
            'TituloObra' => 'required|string|max:129',
            'TipoObra' => 'nullable|string|max:20',
            'CodGenero' => 'nullable|string|max:5',
            'Genero' => 'nullable|string|max:13',
            'PaisOrigen' => 'nullable|string|max:5',
            'AnioProduccion' => 'nullable|integer',
            'Director' => 'nullable|string|max:255',
            'Duracion' => 'nullable|integer|min:0',
            'Idioma' => 'nullable|string|max:5',
            'Guionista' => 'nullable|string|max:255',
            'FichaDoblaje' => 'nullable|boolean',
            'FichaImagen' => 'nullable|boolean',
            // Permitir pasar NMSerie directamente (compatibilidad), pero también soportar controles del modal
            'NMSerie' => 'nullable|integer|exists:obras,NMObra',
            // Los checkboxes llegan como 'on'; no aplicar regla boolean para evitar 422
            'anidar_capitulo' => 'sometimes',
            'crear_obra_general' => 'sometimes',
            'obra_padre_id' => 'nullable|integer|exists:obras,NMObra',
            'nueva_obra_general' => 'nullable|string|max:129',
        ]);

        $data = $this->normalizeObraFields($data);
        // En alta rápida, asegurar valores por defecto mínimos aceptables
        if (!isset($data['Idioma']) || $data['Idioma'] === null || $data['Idioma'] === '') {
            $data['Idioma'] = 'ES';
        }

        // 2) Resolver NMSerie según selección: usar padre existente o crear uno nuevo
        $resolvedNMSerie = $data['NMSerie'] ?? null;
        if ($request->boolean('anidar_capitulo')) {
            if ($request->boolean('crear_obra_general')) {
                $tituloPadre = trim((string) $request->input('nueva_obra_general', ''));
                if ($tituloPadre === '') {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Debe ingresar el título de la obra general a crear.'
                    ], 422);
                }
                // Creamos la obra padre con campos mínimos, reutilizando metadatos si vienen en la hija
                $padreData = $this->normalizeObraFields([
                    'TituloObra' => $tituloPadre,
                    'Genero' => $data['Genero'] ?? null,
                    'CodGenero' => $data['CodGenero'] ?? null,
                    'PaisOrigen' => $data['PaisOrigen'] ?? null,
                    'AnioProduccion' => $data['AnioProduccion'] ?? null,
                    'Director' => $data['Director'] ?? null,
                    'Duracion' => $data['Duracion'] ?? null,
                    'Idioma' => $data['Idioma'] ?? 'ES',
                    'Guionista' => $data['Guionista'] ?? null,
                    'FichaDoblaje' => (int)($data['FichaDoblaje'] ?? 0),
                    'FichaImagen' => (int)($data['FichaImagen'] ?? 0),
                ]);
                $obraPadre = Obra::create($padreData);
                $resolvedNMSerie = $obraPadre->NMObra;
            } else {
                $parentId = $request->input('obra_padre_id');
                if (!$parentId) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Debe seleccionar una obra general o marcar "Crear nueva obra general".'
                    ], 422);
                }
                $resolvedNMSerie = (int) $parentId;
            }
        }

        // 3) Crear la obra hija; si $resolvedNMSerie está presente, será un capítulo
        $obra = Obra::create([
            'NMSerie' => $resolvedNMSerie,
            'TituloObra' => $data['TituloObra'],
            'Genero' => $data['Genero'],
            'CodGenero' => $data['CodGenero'],
            'PaisOrigen' => $data['PaisOrigen'],
            // Para capítulos (cuando NMSerie está presente) forzamos TipoObra a null
            'TipoObra' => $resolvedNMSerie ? null : ($data['TipoObra'] ?? null),
            'AnioProduccion' => $data['AnioProduccion'] ?? null,
            'Director' => $data['Director'] ?? null,
            'Duracion' => $data['Duracion'] ?? null,
            'Idioma' => $data['Idioma'] ?? null,
            'Guionista' => $data['Guionista'] ?? null,
            'FichaDoblaje' => $data['FichaDoblaje'],
            'FichaImagen' => $data['FichaImagen'],
        ]);

        return response()->json([
            'ok' => true,
            'NMObra' => $obra->NMObra,
            'TituloObra' => $obra->TituloObra,
            'NMSerie' => $obra->NMSerie,
        ]);
    }

    // Lista capítulos (obras hijas) de una obra padre
    public function capitulos($NMObra)
    {
        $caps = Obra::query()
            ->select(['NMObra','TituloObra','AnioProduccion','Genero'])
            ->where('NMSerie', $NMObra)
            ->orderBy('TituloObra')
            ->limit(200)
            ->get()
            ->map(fn($c) => [
                'NMObra' => $c->NMObra,
                'TituloObra' => $c->TituloObra,
                'label' => trim($c->TituloObra . ($c->AnioProduccion ? " ({$c->AnioProduccion})" : '')),
            ]);
        return response()->json($caps);
    }

    /**
     * Elimina en cascada emisiones, asignaciones, visionados, bloques, auditorías,
     * elenco y capítulos relacionados a una obra, luego elimina la obra.
     */
    protected function cascadeDeleteObra(Obra $obra): void
    {
        // 1) Eliminar capítulos recursivamente
        foreach ($obra->capitulos()->get() as $cap) {
            $this->cascadeDeleteObra($cap);
        }

        // 2) NO borrar emisiones: solo desasociarlas (obra_id = null). Ya no revertimos títulos porque no se conserva TituloOriginal.
        Emision::where('obra_id', $obra->NMObra)->update(['obra_id' => null]);

        // 4) Elenco (reparto)
        Elenco::where('NMObra', $obra->NMObra)->delete();

        // 5) Finalmente, la obra
        $obra->delete();
    }

    /**
     * Intenta deducir un código de género compacto (2-5 chars) a partir del nombre.
     * Ej.: "Documental" -> "DOC", "Animación" -> "ANIM", "Matinal" -> "MAT".
     */
    protected function guessCodGenero(string $genero): string
    {
        $g = strtolower(trim($genero));
        if ($g === '') return 'ND';
        $map = [
            'documental' => 'DOC',
            'animación' => 'ANIM', 'animacion' => 'ANIM',
            'matinal' => 'MAT',
            'noticias' => 'NOT', 'noticiero' => 'NOT',
            'serie' => 'SER', 'teleserie' => 'TEL',
            'pelicula' => 'PEL', 'película' => 'PEL',
            'deporte' => 'DEP', 'deportes' => 'DEP',
            'entretenimiento' => 'ENT',
            'danza' => 'DAN',
            'doblaje' => 'DOB',
        ];
        if (isset($map[$g])) return $map[$g];
        // fallback: primeras 3-5 letras sin tildes
        $norm = iconv('UTF-8', 'ASCII//TRANSLIT', $g);
        $norm = preg_replace('/[^a-z]/', '', $norm);
        $code = strtoupper(substr($norm, 0, 4));
        return $code !== '' ? $code : 'ND';
    }
}

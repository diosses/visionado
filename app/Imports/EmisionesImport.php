<?php

namespace App\Imports;

use App\Models\Canal;
use App\Models\Emision;
use App\Models\Obra;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importador de Emisiones desde un libro XLSX con múltiples hojas.
 *
 * Flujo:
 * - Se leen filas de "Programas" y se almacenan temporalmente.
 * - Se leen filas de "Resumen" y se agregan los títulos marcados como "PARA VISIONAR".
 * - En AfterImport (o al invocarse manualmente), se filtran las filas de "Programas"
 *   quedando sólo aquellas cuyo título coincide exactamente con los permitidos, y se
 *   crean/actualizan las emisiones correspondientes.
 */
class EmisionesImport implements WithMultipleSheets, WithEvents
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    /** @var array<int,string> */
    public array $errors = [];

    /**
     * Titles marked "PARA VISIONAR" collected from the Resumen sheet (normalized).
     * @var array<string,bool>
     */
    public array $allowedTitles = [];

    /** Filas normalizadas de la hoja Programas pendientes de filtrar e importar. */
    public array $programRows = [];
    public function sheets(): array
    {
        // Map both by index and name to be resilient; we'll buffer Programas rows then process after Resumen.
        return [
            0 => new EmisionesProgramasSheet($this),
            1 => new EmisionesResumenSheet($this),
            'Programas' => new EmisionesProgramasSheet($this),
            'Resumen' => new EmisionesResumenSheet($this),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                // Luego de parsear todas las hojas, ejecutar la importación filtrada
                $this->finalizeImport();
            },
        ];
    }

    /** Llamado por la hoja Programas para almacenar una fila normalizada. */
    public function pushProgramRow(array $data): void
    {
        $this->programRows[] = $data;
    }

    /** Llamado por la hoja Resumen para agregar un título elegible para importar. */
    public function allowTitle(string $title): void
    {
        $norm = $this->normalizeTitle($title);
        if ($norm !== '') $this->allowedTitles[$norm] = true;
    }

    /** Tras procesar ambas hojas, importa únicamente las filas permitidas. */
    public function finalizeImport(): void
    {
        foreach ($this->programRows as $data) {
            $titulo = $this->pickTitulo($data);
            $normTitulo = $this->normalizeTitle($titulo);
            if ($normTitulo === '' || !isset($this->allowedTitles[$normTitulo])) { $this->skipped++; continue; }

            // Canal por nombre (o código si viene)
            $canalNombre = trim((string)($data['canal'] ?? ''));
            $canalNombre = preg_replace('/\s+/', ' ', $canalNombre ?? '');
            $canalCodigo = trim((string)($data['canal_codigo'] ?? ''));
            $canal = null;
            if ($canalCodigo !== '') { $canal = Canal::where('codigo', $canalCodigo)->first(); }
            if (!$canal && $canalNombre !== '') {
                $canal = Canal::whereRaw('LOWER(TRIM(nombre)) = ?', [Str::lower(trim($canalNombre))])->first();
                if (!$canal) {
                    $codigo = $this->generateCanalCodigo($canalNombre);
                    $base = $codigo; $i = 1;
                    while (Canal::where('codigo', $codigo)->exists()) { $suffix = (string)$i++; $codigo = Str::limit($base, max(1, 10 - strlen($suffix)), '') . $suffix; }
                    $canal = Canal::create(['nombre' => $canalNombre, 'codigo' => $codigo, 'activo' => true]);
                }
            }
            if (!$canal) { $this->skipped++; $this->errors[] = 'Fila sin canal'; continue; }

            // Fecha y horas
            $fecha = $this->parseFecha($data['fecha_emision'] ?? $data['fecha'] ?? null);
            $horaInicio = $this->parseHora($data['hora_inicio'] ?? $data['horainicio'] ?? null);
            $horaFin = $this->parseHora($data['hora_fin'] ?? $data['horafin'] ?? null);
            if (!$fecha || !$horaInicio || !$horaFin) { $this->skipped++; $this->errors[] = 'Fecha/Hora inválida'; continue; }

            // Duración en minutos: preferir DurMinut, sino convertir HH:MM
            $duracion = 0;
            $durMinut = (string)($data['durminut'] ?? '');
            if ($durMinut !== '') { $duracion = (int) filter_var($durMinut, FILTER_SANITIZE_NUMBER_INT); }
            else { $duracion = $this->minutesFromHhmm($data['duracion_hhmm'] ?? $data['duracion_hh_mm'] ?? ''); }

            // Obra opcional exacta
            $obra = null;
            if ($titulo !== '') { $obra = Obra::whereRaw('LOWER(TRIM(TituloObra)) = ?', [Str::lower(trim($titulo))])->first(); }

            // Tipo heurístico
            $gen = Str::lower((string)($data['genero'] ?? ''));
            $sub = Str::lower((string)($data['subgenero'] ?? ''));
            $tipo = 'miscelaneo';
            if (str_contains($gen, 'serie') || str_contains($sub, 'serie')) { $tipo = 'serie'; }
            elseif ($obra && ($obra->TipoObra === 'Serie')) { $tipo = 'serie'; }

            // Upsert / detecta duplicado por canal + fecha + hora_inicio (+ obra NULL vs ID)
            $existingQ = Emision::query()
                ->where('canal_id', $canal->id)
                ->whereDate('fecha_emision', $fecha->toDateString())
                ->where('hora_inicio', $horaInicio);
            if ($obra) { $existingQ->where('obra_id', $obra->NMObra); } else { $existingQ->whereNull('obra_id'); }
            $existing = $existingQ->first();

            if ($existing) {
                $existing->update([
                    'hora_fin' => $horaFin,
                    'duracion' => $duracion ?: $existing->duracion,
                    'protegido' => true,
                    'tipo' => $tipo,
                    'TituloEmision' => $titulo !== '' ? $titulo : $existing->TituloEmision,
                    'fuente_datos' => 'xlsx',
                ]);
                $this->updated++;
            } else {
                Emision::create([
                    'obra_id' => $obra?->NMObra,
                    'TituloEmision' => $titulo !== '' ? $titulo : null,
                    'canal_id' => $canal->id,
                    'fecha_emision' => $fecha->toDateString(),
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'duracion' => $duracion,
                    'protegido' => true,
                    'tipo' => $tipo,
                    'episodio' => null,
                    'fuente_datos' => 'xlsx',
                ]);
                $this->created++;
            }
        }
    // Liberar memoria
        $this->programRows = [];
    }

    /**
     * Picks the best candidate for the title column from normalized headers
     * like 'titulo_descripcion', 'titulodescripcion', 'titulo', etc.
     * @param array<string,mixed> $data
     */
    public function pickTitulo(array $data): string
    {
        $candidates = [
            'titulo_descripcion', 'titulodescripcion', 'titulo', 'titulo_descr', 'descripcion', 'descripcion_titulo', 'titulo_programa', 'programa', 'emision'
        ];
        foreach ($candidates as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string)$data[$k]);
                if ($v !== '') return $v;
            }
        }
        // Regex fallback: look for any key that contains both 'titulo' and 'descr'
        foreach ($data as $k => $v) {
            $kk = (string)$k;
            if (preg_match('/titulo/i', $kk) && preg_match('/desc/i', $kk)) {
                $v2 = trim((string)$v);
                if ($v2 !== '') return $v2;
            }
        }
        // Last resort: any key that contains 'titulo'
        foreach ($data as $k => $v) {
            if (preg_match('/titulo/i', (string)$k)) {
                $v2 = trim((string)$v);
                if ($v2 !== '') return $v2;
            }
        }
        return '';
    }

    /** Normaliza encabezados/llaves de planilla y devuelve un array asociativo. */
    public function normalizeRow(Collection|array $row): array
    {
        $src = $row instanceof Collection ? $row->toArray() : $row;
        $data = [];
        foreach ($src as $k => $v) {
            $key = $this->normalizeHeader((string)$k);
            $data[$key] = $v;
        }
        return $data;
    }

    /** Normaliza el título para comparación exacta (trim + espacios + ascii + casefold). */
    public function normalizeTitle(string $t): string
    {
        $t = Str::ascii($t);
        $t = trim(preg_replace('/\s+/', ' ', $t));
        $t = Str::lower($t);
        return $t;
    }

    private function parseFecha($value): ?Carbon
    {
        if ($value === null || $value === '') return null;
        // Excel numeric date
    if (is_numeric($value)) {
            try { return Carbon::instance(ExcelDate::excelToDateTimeObject((float)$value)); } catch (\Throwable) {}
        }
        $v = trim((string)$value);
    foreach (['d/m/Y','d/m/y','Y-m-d','d-m-Y'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $v); } catch (\Throwable) {}
        }
        try { return Carbon::parse($v); } catch (\Throwable) { return null; }
    }

    private function parseHora($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            // Fracción de día a H:i:s
            $seconds = (float)$value * 24 * 3600;
            $h = floor($seconds/3600);
            $m = floor(($seconds%3600)/60);
            $s = floor($seconds%60);
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
        $v = trim((string)$value);
    // Manejar horas con coma decimal, ej. "1,5"
        if (preg_match('/^\d+(?:[\.,]\d+)?$/', $v)) {
            $hours = (float)str_replace(',', '.', $v);
            $total = (int)round($hours * 3600);
            $h = floor($total/3600); $m = floor(($total%3600)/60); $s = $total%60;
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
        if (preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $v)) {
            return strlen($v) === 5 ? ($v.':00') : $v;
        }
        return null;
    }

    private function minutesFromHhmm($value): int
    {
        $v = trim((string)$value);
        if ($v === '') return 0;
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m)) {
            return ((int)$m[1]) * 60 + (int)$m[2];
        }
        return 0;
    }

    private function generateCanalCodigo(string $nombre): string
    {
    // Normaliza y se queda con A-Z0-9, mayúsculas, sin tildes, máx. 10 caracteres
        $base = Str::upper(Str::ascii($nombre));
        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'CANAL';
    // Si quedó muy corto, completa con letras fijas para asegurar longitud mínima
        $base = str_pad($base, 4, 'X');
        return Str::limit($base, 10, '');
    }

    /** Normaliza un encabezado de columna a snake-case ascii seguro. */
    public function normalizeHeader(string $h): string
    {
        $key = Str::of($h)
            ->trim()->lower()->pipe(fn($s) => Str::ascii($s))
            ->replace([' ', '-', '/', '[', ']', '|'], '_')
            ->replace('%', 'pct')
            ->replace('__', '_')->replace('__', '_')
            ->toString();
        return preg_replace('/[^a-z0-9_]/', '', $key) ?: 'col';
    }

    /**
     * Convierte filas crudas en filas asociativas detectando el encabezado entre
     * las primeras 3 filas no vacías. Tolera filas vacías iniciales.
     * @return array<int, array<string, mixed>>
     */
    public function parseAssocRows(Collection $rows): array
    {
        if ($rows->isEmpty()) return [];

        // Buscar encabezado entre las primeras 3 filas no vacías
        $headerRow = null; $headerIndex = null;
        foreach ($rows->take(3) as $idx => $r) {
            $arr = array_values(array_filter($r->toArray(), fn($v) => $v !== null && $v !== ''));
            if (count($arr) >= 2) {
                // Si contiene alguna palabra clave, asumimos encabezado
                $joined = Str::lower(Str::ascii(implode(' ', $arr)));
                if (str_contains($joined, 'titulo') || str_contains($joined, 'programa') || str_contains($joined, 'fecha') || str_contains($joined, 'visionado')) {
                    $headerRow = $r->toArray();
                    $headerIndex = $idx;
                    break;
                }
            }
        }
        // Fallback: primera fila
        if ($headerRow === null) { $headerRow = $rows->first()->toArray(); $headerIndex = 0; }

        // Normalizar encabezados
        $headers = [];
        foreach ($headerRow as $h) { $headers[] = $this->normalizeHeader((string)$h); }

        // Asociar filas subsiguientes
        $assoc = [];
        foreach ($rows->slice($headerIndex + 1) as $r) {
            $vals = array_values($r->toArray());
            if (empty(array_filter($vals, fn($v) => $v !== null && $v !== ''))) continue; // fila vacía
            // Alinear tamaño
            $vals = array_pad($vals, count($headers), null);
            $row = array_combine($headers, array_slice($vals, 0, count($headers)));
            $assoc[] = $row;
        }
        return $assoc;
    }
}

// Sheet importers
class EmisionesProgramasSheet implements ToCollection
{
    public function __construct(private EmisionesImport $parent) {}
    public function collection(Collection $rows)
    {
        $assocRows = $this->parent->parseAssocRows($rows);
        foreach ($assocRows as $row) {
            $data = $this->parent->normalizeRow($row);
            $this->parent->pushProgramRow($data);
        }
    }
}

class EmisionesResumenSheet implements ToCollection
{
    public function __construct(private EmisionesImport $parent) {}
    public function collection(Collection $rows)
    {
        $assocRows = $this->parent->parseAssocRows($rows);
        foreach ($assocRows as $row) {
            $data = $this->parent->normalizeRow($row);
            // If Visionado contains "PARA VISIONAR", collect its title
            $visionado = Str::lower(Str::ascii(trim((string)($data['visionado'] ?? ''))));
            if ($visionado !== '' && str_contains($visionado, 'para visionar')) {
                $titulo = $this->parent->pickTitulo($data);
                if ($titulo !== '') $this->parent->allowTitle($titulo);
            }
        }
    }
}

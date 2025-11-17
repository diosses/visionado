<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Visionado
 * Progreso del análisis de una asignación (modo secuencia/minutaje y fechas).
 */
class Visionado extends Model
{
    protected $table = 'visionados';
    protected $fillable = [
        'asignacion_id', 'fecha_inicio', 'fecha_fin', 'estado', 'modo'
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        // estado tinyint, modo tinyint(1)
        'estado' => 'integer',
        'modo' => 'boolean',
    ];

    /**
     * Estado codes (sparse mapping as requested)
     * 0 = pendiente
     * 1 = en progreso
     * 3 = completada
     * 4 = auditada
     */
    public const ESTADO_PENDIENTE  = 0;
    public const ESTADO_EN_PROGRESO = 1;
    public const ESTADO_COMPLETADA = 3;
    public const ESTADO_AUDITADA  = 4;

    /**
     * Modo codes
     * 0 = secuenciado
     * 1 = minutado
     */
    public const MODO_SECUENCIADO = 0;
    public const MODO_MINUTADO = 1;

    /**
     * Normalized label map for estados (for UI / legacy display)
     */
    public static array $ESTADO_LABELS = [
        self::ESTADO_PENDIENTE => 'pendiente',
        self::ESTADO_EN_PROGRESO => 'en_progreso',
        self::ESTADO_COMPLETADA => 'completada',
        self::ESTADO_AUDITADA => 'auditada',
    ];

    /**
     * Accessor: human label of estado (e.g. 'pendiente')
     */
    public function getEstadoLabelAttribute(): string
    {
        return self::$ESTADO_LABELS[$this->estado] ?? 'pendiente';
    }

    /**
     * Accessor: human label of modo
     */
    public function getModoLabelAttribute(): string
    {
        return $this->modo ? 'minutado' : 'secuenciado';
    }

    /**
     * Mutator: ensure estado stored as int code; accepts int or string.
     */
    public function setEstadoAttribute($value): void
    {
        if ($value === null || $value === '') { $this->attributes['estado'] = self::ESTADO_PENDIENTE; return; }
        if (is_numeric($value)) { $code = (int)$value; $this->attributes['estado'] = $code; return; }
        $normalized = strtolower(trim((string)$value));
        $map = [
            'pendiente' => self::ESTADO_PENDIENTE,
            'en_progreso' => self::ESTADO_EN_PROGRESO,
            'en-progreso' => self::ESTADO_EN_PROGRESO,
            'completada' => self::ESTADO_COMPLETADA,
            'auditada' => self::ESTADO_AUDITADA,
        ];
        $this->attributes['estado'] = $map[$normalized] ?? self::ESTADO_PENDIENTE;
    }

    /**
     * Mutator: ensure modo stored as boolean tinyint (0/1); accepts variants.
     */
    public function setModoAttribute($value): void
    {
        if (is_bool($value)) { $this->attributes['modo'] = $value ? 1 : 0; return; }
        if (is_numeric($value)) { $this->attributes['modo'] = ((int)$value) ? 1 : 0; return; }
        $normalized = strtolower(trim((string)$value));
        $this->attributes['modo'] = in_array($normalized, ['1','true','t','yes','y','minutado','minutaje'], true) ? 1 : 0;
    }

    public function asignacion()
    {
        return $this->belongsTo(Asignacion::class);
    }

    public function bloques()
    {
        return $this->hasMany(Bloque::class);
    }

    public function auditoria()
    {
        return $this->hasOne(Auditoria::class);
    }
}
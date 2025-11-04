<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $fillable = [
        'horario_id',
        'docente_id',
        'fecha',
        'hora_registro',
        'estado',
        'metodo_registro',
        'observaciones',
        'registrado_por',
        'latitud',
        'longitud',
        'direccion_geolocalizacion',
        'distancia_aula',
        'geolocalizacion_valida'
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_registro' => 'datetime:H:i'
    ];

    /**
     * Relación con horario
     */
    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * Relación con docente
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    /**
     * Relación con usuario que registró
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Scope para asistencias por fecha
     */
    public function scopePorFecha($query, $fecha)
    {
        return $query->where('fecha', $fecha);
    }

    /**
     * Scope para asistencias por estado
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para asistencias de docente
     */
    public function scopeDeDocente($query, $docenteId)
    {
        return $query->where('docente_id', $docenteId);
    }

    /**
     * Scope para asistencias por rango de fechas
     */
    public function scopePorRangoFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    /**
     * Verificar si la asistencia es tardanza
     */
    public function esTardanza()
    {
        if ($this->estado !== 'presente') {
            return false;
        }

        $horaInicio = \Carbon\Carbon::parse($this->horario->hora_inicio);
        $horaRegistro = \Carbon\Carbon::parse($this->hora_registro);

        // Considerar tardanza si llega 15 minutos después del inicio
        return $horaRegistro->diffInMinutes($horaInicio) > 15;
    }

    /**
     * Obtener información completa de la asistencia
     */
    public function obtenerInformacionCompleta()
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->format('d/m/Y'),
            'hora_registro' => $this->hora_registro,
            'estado' => $this->estado,
            'metodo_registro' => $this->metodo_registro,
            'observaciones' => $this->observaciones,
            'materia' => $this->horario->grupo->materia->nombre,
            'sigla' => $this->horario->grupo->materia->sigla,
            'grupo' => $this->horario->grupo->numero_grupo,
            'docente' => $this->docente->user->nombre_completo,
            'aula' => $this->horario->aula->nombre,
            'dia' => $this->horario->nombre_dia,
            'hora_inicio' => $this->horario->hora_inicio,
            'hora_fin' => $this->horario->hora_fin,
            'es_tardanza' => $this->esTardanza()
        ];
    }

    /**
     * Obtener estadísticas de asistencias
     */
    public static function obtenerEstadisticas($filtros = [])
    {
        $query = self::query();

        if (isset($filtros['docente_id'])) {
            $query->where('docente_id', $filtros['docente_id']);
        }

        if (isset($filtros['fecha_inicio'])) {
            $query->where('fecha', '>=', $filtros['fecha_inicio']);
        }

        if (isset($filtros['fecha_fin'])) {
            $query->where('fecha', '<=', $filtros['fecha_fin']);
        }

        $asistencias = $query->get();

        return [
            'total' => $asistencias->count(),
            'presentes' => $asistencias->where('estado', 'presente')->count(),
            'ausentes' => $asistencias->where('estado', 'ausente')->count(),
            'tardanzas' => $asistencias->where('estado', 'tardanza')->count(),
            'justificados' => $asistencias->where('estado', 'justificado')->count(),
            'porcentaje_asistencia' => $asistencias->count() > 0
                ? round(($asistencias->where('estado', 'presente')->count() / $asistencias->count()) * 100, 2)
                : 0
        ];
    }
}

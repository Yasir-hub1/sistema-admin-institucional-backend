<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    protected $fillable = [
        'user_id',
        'codigo_docente',
        'especialidad',
        'grado_academico',
        'telefono',
        'direccion',
        'carga_horaria_maxima',
        'disponibilidad_horaria'
    ];

    protected $casts = [
        'disponibilidad_horaria' => 'array'
    ];

    /**
     * Relación con usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con horarios
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * Relación con asistencias
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Scope para docentes activos
     */
    public function scopeActivos($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('activo', true);
        });
    }

    /**
     * Calcular carga horaria del docente para una gestión específica
     */
    public function calcularCargaHoraria($gestionId = null)
    {
        $query = $this->horarios();

        if ($gestionId) {
            $query->whereHas('grupo', function ($q) use ($gestionId) {
                $q->where('gestion_id', $gestionId);
            });
        }

        $horarios = $query->get();

        $totalHoras = 0;
        foreach ($horarios as $horario) {
            $inicio = \Carbon\Carbon::parse($horario->hora_inicio);
            $fin = \Carbon\Carbon::parse($horario->hora_fin);
            $totalHoras += $inicio->diffInMinutes($fin) / 60;
        }

        return $totalHoras;
    }

    /**
     * Verificar si excede la carga horaria máxima
     */
    public function excedeCargaMaxima($gestionId = null)
    {
        $cargaActual = $this->calcularCargaHoraria($gestionId);
        $maxima = $this->carga_horaria_maxima ?? 40;
        return $cargaActual > $maxima;
    }

    /**
     * Obtener disponibilidad en un día y hora específicos
     */
    public function estaDisponible($diaSemana, $horaInicio, $horaFin)
    {
        if (!$this->disponibilidad_horaria) {
            return true; // Sin restricciones
        }

        $disponibilidad = $this->disponibilidad_horaria;
        if (!isset($disponibilidad[$diaSemana])) {
            return true; // Día no especificado = disponible
        }

        $horasDisponibles = $disponibilidad[$diaSemana];
        foreach ($horasDisponibles as $rango) {
            if (isset($rango['inicio']) && isset($rango['fin'])) {
                if ($horaInicio >= $rango['inicio'] && $horaFin <= $rango['fin']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Obtener estadísticas de asistencia
     */
    public function obtenerEstadisticasAsistencia($fechaInicio = null, $fechaFin = null)
    {
        $query = $this->asistencias();

        if ($fechaInicio) {
            $query->where('fecha', '>=', $fechaInicio);
        }

        if ($fechaFin) {
            $query->where('fecha', '<=', $fechaFin);
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

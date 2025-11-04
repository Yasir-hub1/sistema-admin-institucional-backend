<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aula extends Model
{
    protected $fillable = [
        'codigo_aula',
        'nombre',
        'capacidad',
        'edificio',
        'piso',
        'tipo',
        'activa',
        'latitud',
        'longitud'
    ];

    protected $casts = [
        'activa' => 'boolean'
    ];

    /**
     * Relación con horarios
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * Scope para aulas activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Scope para aulas por tipo
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para aulas por edificio
     */
    public function scopePorEdificio($query, $edificio)
    {
        return $query->where('edificio', $edificio);
    }

    /**
     * Verificar disponibilidad de aula en un horario específico
     */
    public function estaDisponible($dia, $horaInicio, $horaFin, $excluirHorarioId = null)
    {
        $query = $this->horarios()
            ->where('dia_semana', $dia)
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                  ->orWhereBetween('hora_fin', [$horaInicio, $horaFin])
                  ->orWhere(function ($q2) use ($horaInicio, $horaFin) {
                      $q2->where('hora_inicio', '<=', $horaInicio)
                         ->where('hora_fin', '>=', $horaFin);
                  });
            });

        if ($excluirHorarioId) {
            $query->where('id', '!=', $excluirHorarioId);
        }

        return $query->count() === 0;
    }

    /**
     * Obtener ocupación de aula por día
     */
    public function obtenerOcupacionPorDia($dia)
    {
        return $this->horarios()
            ->where('dia_semana', $dia)
            ->with(['grupo.materia', 'docente.user'])
            ->get();
    }
}

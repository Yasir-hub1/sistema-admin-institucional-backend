<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GestionAcademica extends Model
{
    protected $table = 'gestiones_academicas';

    protected $fillable = [
        'nombre',
        'año',
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'activa'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activa' => 'boolean'
    ];

    /**
     * Relación con grupos
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'gestion_id');
    }

    /**
     * Scope para gestiones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Scope para gestiones por año
     */
    public function scopePorAnio($query, $año)
    {
        return $query->where('año', $año);
    }

    /**
     * Obtener la gestión académica activa actual
     */
    public static function getActiva()
    {
        return self::activas()->first();
    }

    /**
     * Verificar si la gestión está en curso
     */
    public function estaEnCurso()
    {
        $hoy = now()->toDateString();
        return $this->fecha_inicio <= $hoy && $this->fecha_fin >= $hoy;
    }

    /**
     * Obtener estadísticas de la gestión
     */
    public function obtenerEstadisticas()
    {
        $grupos = $this->grupos()->with(['materia', 'horarios'])->get();

        return [
            'total_grupos' => $grupos->count(),
            'total_materias' => $grupos->pluck('materia_id')->unique()->count(),
            'total_horarios' => $grupos->sum(function ($grupo) {
                return $grupo->horarios->count();
            }),
            'total_docentes' => $grupos->flatMap->horarios->pluck('docente_id')->unique()->count()
        ];
    }
}

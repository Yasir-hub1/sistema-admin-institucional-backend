<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materia extends Model
{
    protected $fillable = [
        'codigo_materia',
        'nombre',
        'sigla',
        'horas_teoricas',
        'horas_practicas',
        'nivel',
        'semestre'
    ];

    /**
     * Relación con grupos
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }

    /**
     * Accessor para horas totales
     */
    public function getHorasTotalesAttribute()
    {
        return $this->horas_teoricas + $this->horas_practicas;
    }

    /**
     * Scope para materias por nivel
     */
    public function scopePorNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }

    /**
     * Scope para materias por semestre
     */
    public function scopePorSemestre($query, $semestre)
    {
        return $query->where('semestre', $semestre);
    }

    /**
     * Scope para búsqueda de materias
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'like', "%{$termino}%")
              ->orWhere('sigla', 'like', "%{$termino}%")
              ->orWhere('codigo_materia', 'like', "%{$termino}%");
        });
    }
}

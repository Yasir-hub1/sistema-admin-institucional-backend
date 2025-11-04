<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    protected $fillable = [
        'materia_id',
        'gestion_id',
        'numero_grupo',
        'cupo_maximo'
    ];

    /**
     * Relación con materia
     */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    /**
     * Relación con gestión académica
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionAcademica::class, 'gestion_id');
    }

    /**
     * Relación con horarios
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * Scope para grupos de gestión activa
     */
    public function scopeDeGestionActiva($query)
    {
        return $query->whereHas('gestion', function ($q) {
            $q->where('activa', true);
        });
    }

    /**
     * Scope para grupos por materia
     */
    public function scopePorMateria($query, $materiaId)
    {
        return $query->where('materia_id', $materiaId);
    }

    /**
     * Scope para grupos por gestión
     */
    public function scopePorGestion($query, $gestionId)
    {
        return $query->where('gestion_id', $gestionId);
    }

    /**
     * Obtener nombre completo del grupo
     */
    public function getNombreCompletoAttribute()
    {
        return $this->materia->sigla . ' - Grupo ' . $this->numero_grupo;
    }

    /**
     * Obtener carga horaria total del grupo
     */
    public function obtenerCargaHoraria()
    {
        $horarios = $this->horarios;
        $totalHoras = 0;

        foreach ($horarios as $horario) {
            $inicio = \Carbon\Carbon::parse($horario->hora_inicio);
            $fin = \Carbon\Carbon::parse($horario->hora_fin);
            $totalHoras += $inicio->diffInMinutes($fin) / 60;
        }

        return $totalHoras;
    }
}

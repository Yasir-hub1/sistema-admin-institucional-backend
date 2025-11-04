<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Horario extends Model
{
    protected $fillable = [
        'grupo_id',
        'docente_id',
        'aula_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin'
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i'
    ];

    /**
     * Relación con grupo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Relación con docente
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    /**
     * Relación con aula
     */
    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    /**
     * Relación con asistencias
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Scope para horarios por día
     */
    public function scopePorDia($query, $dia)
    {
        return $query->where('dia_semana', $dia);
    }

    /**
     * Scope para horarios de docente
     */
    public function scopeDeDocente($query, $docenteId)
    {
        return $query->where('docente_id', $docenteId);
    }

    /**
     * Scope para horarios de aula
     */
    public function scopeDeAula($query, $aulaId)
    {
        return $query->where('aula_id', $aulaId);
    }

    /**
     * Scope para horarios de grupo
     */
    public function scopeDeGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Obtener nombre del día de la semana
     */
    public function getNombreDiaAttribute()
    {
        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];

        return $dias[$this->dia_semana] ?? 'Desconocido';
    }

    /**
     * Obtener duración del horario en horas
     */
    public function getDuracionHorasAttribute()
    {
        $inicio = \Carbon\Carbon::parse($this->hora_inicio);
        $fin = \Carbon\Carbon::parse($this->hora_fin);
        return $inicio->diffInMinutes($fin) / 60;
    }

    /**
     * Verificar si hay conflicto con otro horario
     */
    public function tieneConflictoCon($otroHorario)
    {
        if ($this->id === $otroHorario->id) {
            return false;
        }

        // Mismo día
        if ($this->dia_semana !== $otroHorario->dia_semana) {
            return false;
        }

        // Mismo docente o misma aula
        if ($this->docente_id !== $otroHorario->docente_id && $this->aula_id !== $otroHorario->aula_id) {
            return false;
        }

        // Verificar solapamiento de horarios
        $inicio1 = \Carbon\Carbon::parse($this->hora_inicio);
        $fin1 = \Carbon\Carbon::parse($this->hora_fin);
        $inicio2 = \Carbon\Carbon::parse($otroHorario->hora_inicio);
        $fin2 = \Carbon\Carbon::parse($otroHorario->hora_fin);

        return $inicio1 < $fin2 && $fin1 > $inicio2;
    }

    /**
     * Obtener información completa del horario
     */
    public function obtenerInformacionCompleta()
    {
        return [
            'id' => $this->id,
            'dia' => $this->nombre_dia,
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'duracion' => $this->duracion_horas,
            'materia' => $this->grupo->materia->nombre,
            'sigla' => $this->grupo->materia->sigla,
            'grupo' => $this->grupo->numero_grupo,
            'docente' => $this->docente->user->nombre_completo,
            'aula' => $this->aula->nombre,
            'edificio' => $this->aula->edificio,
            'piso' => $this->aula->piso
        ];
    }
}

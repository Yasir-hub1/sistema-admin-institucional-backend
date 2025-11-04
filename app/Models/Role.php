<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    /**
     * Relación con usuarios
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    /**
     * Relación con permisos (muchos a muchos)
     */
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'role_permiso', 'role_id', 'permiso_id');
    }

    /**
     * Verificar si el rol tiene un permiso específico
     */
    public function tienePermiso($permisoNombre)
    {
        return $this->permisos()->where('nombre', $permisoNombre)->exists();
    }

    /**
     * Asignar permisos al rol
     */
    public function asignarPermisos(array $permisoIds)
    {
        $this->permisos()->sync($permisoIds);
    }

    /**
     * Scope para roles activos
     */
    public function scopeActivos($query)
    {
        return $query->whereNotNull('nombre');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = [
        'nombre',
        'modulo',
        'accion',
        'descripcion'
    ];

    /**
     * Relación con roles (muchos a muchos)
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permiso', 'permiso_id', 'role_id');
    }
}

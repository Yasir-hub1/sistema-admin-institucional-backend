<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol_id',
        'activo'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Relación con rol
     */
    public function rol()
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    /**
     * Relación con docente (si aplica)
     */
    public function docente()
    {
        return $this->hasOne(Docente::class);
    }

    /**
     * Accessor para nombre completo
     */
    public function getNombreCompletoAttribute()
    {
        return $this->name;
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function tieneRol($rol)
    {
        return $this->rol && $this->rol->nombre === $rol;
    }

    /**
     * Verificar si el usuario es administrador
     */
    public function esAdmin()
    {
        return $this->tieneRol('admin');
    }

    /**
     * Verificar si el usuario es coordinador
     */
    public function esCoordinador()
    {
        return $this->tieneRol('coordinador');
    }

    /**
     * Verificar si el usuario es docente
     */
    public function esDocente()
    {
        return $this->tieneRol('docente');
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function tienePermiso($permisoNombre)
    {
        if (!$this->rol) {
            return false;
        }

        // Cargar permisos si no están cargados
        if (!$this->rol->relationLoaded('permisos')) {
            $this->rol->load('permisos');
        }

        return $this->rol->permisos()->where('nombre', $permisoNombre)->exists();
    }

    /**
     * Verificar si el usuario tiene un permiso por módulo y acción
     */
    public function tienePermisoPorModuloAccion($modulo, $accion)
    {
        if (!$this->rol) {
            return false;
        }

        // Cargar permisos si no están cargados
        if (!$this->rol->relationLoaded('permisos')) {
            $this->rol->load('permisos');
        }

        return $this->rol->permisos()
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->exists();
    }

    /**
     * Obtener todos los permisos del usuario
     */
    public function obtenerPermisos()
    {
        if (!$this->rol) {
            return collect();
        }

        // Cargar permisos si no están cargados
        if (!$this->rol->relationLoaded('permisos')) {
            $this->rol->load('permisos');
        }

        return $this->rol->permisos;
    }
}

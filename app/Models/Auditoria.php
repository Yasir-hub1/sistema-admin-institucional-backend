<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    protected $fillable = [
        'user_id',
        'accion',
        'modelo',
        'modelo_id',
        'descripcion',
        'datos_anteriores',
        'datos_nuevos',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function registrar($accion, $modelo, $modeloId = null, $descripcion = '', $datosAnteriores = null, $datosNuevos = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'modelo' => $modelo,
            'modelo_id' => $modeloId,
            'descripcion' => $descripcion,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public function scopePorModelo($query, $modelo)
    {
        return $query->where('modelo', $modelo);
    }

    public function scopePorAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }
}

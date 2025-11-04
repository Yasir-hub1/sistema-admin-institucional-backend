<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'user_id',
        'tipo',
        'titulo',
        'mensaje',
        'accion_url',
        'leida',
        'leida_at',
        'datos_adicionales'
    ];

    protected $casts = [
        'leida' => 'boolean',
        'leida_at' => 'datetime',
        'datos_adicionales' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marcarComoLeida()
    {
        $this->update([
            'leida' => true,
            'leida_at' => now()
        ]);
    }

    public static function crear($tipo, $titulo, $mensaje, $userId = null, $accionUrl = null, $datosAdicionales = null)
    {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'accion_url' => $accionUrl,
            'datos_adicionales' => $datosAdicionales
        ]);
    }

    public function scopeNoLeidas($query)
    {
        return $query->where('leida', false);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}

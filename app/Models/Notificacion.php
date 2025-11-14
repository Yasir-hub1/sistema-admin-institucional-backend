<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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
        try {
            $userId = $userId ?? auth()->id();

            if (!$userId) {
                Log::warning('Notificacion::crear - No se pudo obtener user_id', [
                    'tipo' => $tipo,
                    'titulo' => $titulo
                ]);
                return null;
            }

            $notificacion = self::create([
                'user_id' => $userId,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'accion_url' => $accionUrl,
                'leida' => false,
                'datos_adicionales' => $datosAdicionales
            ]);

            Log::info('Notificación creada exitosamente', [
                'id' => $notificacion->id,
                'user_id' => $userId,
                'tipo' => $tipo,
                'titulo' => $titulo
            ]);

            return $notificacion;
        } catch (\Exception $e) {
            $userIdLog = $userId ? $userId : 'null';
            Log::error('Error al crear notificación', [
                'error' => $e->getMessage(),
                'tipo' => $tipo,
                'titulo' => $titulo,
                'user_id' => $userIdLog,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
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

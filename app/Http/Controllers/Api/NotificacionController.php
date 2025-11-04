<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    /**
     * Obtener notificaciones del usuario autenticado
     */
    public function index(Request $request)
    {
        try {
            $query = Notificacion::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc');

            if ($request->has('leida')) {
                $query->where('leida', $request->leida === 'true');
            }

            if ($request->has('tipo')) {
                $query->porTipo($request->tipo);
            }

            $notificaciones = $query->paginate($request->get('per_page', 15));

            // Contar no leídas
            $noLeidas = Notificacion::where('user_id', Auth::id())
                ->noLeidas()
                ->count();

            return response()->json([
                'success' => true,
                'data' => $notificaciones,
                'no_leidas' => $noLeidas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarLeida($id)
    {
        try {
            $notificacion = Notificacion::where('user_id', Auth::id())
                ->findOrFail($id);

            $notificacion->marcarComoLeida();

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída',
                'data' => $notificacion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas como leídas
     */
    public function marcarTodasLeidas()
    {
        try {
            Notificacion::where('user_id', Auth::id())
                ->noLeidas()
                ->update([
                    'leida' => true,
                    'leida_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Todas las notificaciones fueron marcadas como leídas'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Contar notificaciones no leídas
     */
    public function contarNoLeidas()
    {
        try {
            $count = Notificacion::where('user_id', Auth::id())
                ->noLeidas()
                ->count();

            return response()->json([
                'success' => true,
                'data' => ['count' => $count]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al contar notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificación
     */
    public function destroy($id)
    {
        try {
            $notificacion = Notificacion::where('user_id', Auth::id())
                ->findOrFail($id);

            $notificacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

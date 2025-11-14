<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificacionController extends Controller
{
    /**
     * Obtener notificaciones del usuario autenticado
     */
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $query = Notificacion::where('user_id', $userId)
                ->orderBy('created_at', 'desc');

            // Filtro por leída/no leída
            if ($request->filled('leida')) {
                $leida = filter_var($request->leida, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($leida !== null) {
                    $query->where('leida', $leida);
                }
            }

            // Filtro por tipo
            if ($request->filled('tipo')) {
                $query->porTipo($request->tipo);
            }

            // Búsqueda general
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'ILIKE', "%{$search}%")
                      ->orWhere('mensaje', 'ILIKE', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 15);
            $notificaciones = $query->paginate($perPage);

            // Contar no leídas
            $noLeidas = Notificacion::where('user_id', $userId)
                ->noLeidas()
                ->count();

            Log::info('Notificaciones listadas', [
                'user_id' => $userId,
                'total' => $notificaciones->total(),
                'no_leidas' => $noLeidas,
                'current_page' => $notificaciones->currentPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => $notificaciones,
                'no_leidas' => $noLeidas,
                'message' => 'Notificaciones obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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

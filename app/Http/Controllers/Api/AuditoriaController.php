<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditoriaController extends Controller
{
    /**
     * Obtener registros de auditoría con filtros
     */
    public function index(Request $request)
    {
        try {
            $query = Auditoria::with('user')
                ->orderBy('created_at', 'desc');

            // Filtro por modelo
            if ($request->filled('modelo')) {
                $query->porModelo($request->modelo);
            }

            // Filtro por acción
            if ($request->filled('accion')) {
                $query->porAccion($request->accion);
            }

            // Filtro por usuario
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filtro por rango de fechas
            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $query->whereBetween('created_at', [
                    $request->fecha_inicio . ' 00:00:00',
                    $request->fecha_fin . ' 23:59:59'
                ]);
            }

            // Búsqueda general
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('descripcion', 'ILIKE', "%{$search}%")
                      ->orWhere('modelo', 'ILIKE', "%{$search}%")
                      ->orWhereHas('user', function ($q) use ($search) {
                          $q->where('name', 'ILIKE', "%{$search}%");
                      });
                });
            }

            $perPage = $request->get('per_page', 20);
            $auditoria = $query->paginate($perPage);

            Log::info('Auditoría listada', [
                'total' => $auditoria->total(),
                'per_page' => $auditoria->perPage(),
                'current_page' => $auditoria->currentPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => $auditoria,
                'message' => 'Registros de auditoría obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar auditoría', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros de auditoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener auditoría por modelo
     */
    public function porModelo($modelo, Request $request)
    {
        try {
            $query = Auditoria::with('user')
                ->porModelo($modelo)
                ->orderBy('created_at', 'desc');

            // Filtro por modelo_id específico
            if ($request->filled('modelo_id')) {
                $query->where('modelo_id', $request->modelo_id);
            }

            // Filtro por acción
            if ($request->filled('accion')) {
                $query->porAccion($request->accion);
            }

            $perPage = $request->get('per_page', 15);
            $auditoria = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $auditoria,
                'message' => "Auditoría de {$modelo} obtenida exitosamente"
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener auditoría por modelo', [
                'modelo' => $modelo,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener auditoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un registro de auditoría específico
     */
    public function show($id)
    {
        try {
            $auditoria = Auditoria::with('user')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $auditoria
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de auditoría no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}


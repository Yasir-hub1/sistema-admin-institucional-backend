<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermisoController extends Controller
{
    /**
     * Listar todos los permisos
     */
    public function index(Request $request)
    {
        try {
            $query = Permiso::withCount('roles');

            // Filtro por módulo
            if ($request->has('modulo')) {
                $query->where('modulo', $request->modulo);
            }

            // Filtro por búsqueda
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('modulo', 'like', "%{$search}%")
                      ->orWhere('accion', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'modulo');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 100);
            $permisos = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $permisos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los permisos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener permisos agrupados por módulo
     */
    public function porModulo()
    {
        try {
            $permisos = Permiso::orderBy('modulo')->orderBy('accion')->get();
            $agrupados = $permisos->groupBy('modulo');

            return response()->json([
                'success' => true,
                'data' => $agrupados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los permisos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

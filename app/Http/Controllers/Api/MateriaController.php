<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MateriaController extends Controller
{
    /**
     * Listar todas las materias con filtros opcionales
     */
    public function index(Request $request)
    {
        try {
            $query = Materia::query();

            // Filtro por búsqueda (nombre, código o sigla)
            if ($request->has('search')) {
                $query->buscar($request->search);
            }

            // Filtro por nivel
            if ($request->has('nivel')) {
                $query->porNivel($request->nivel);
            }

            // Filtro por semestre
            if ($request->has('semestre')) {
                $query->porSemestre($request->semestre);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'nombre');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $materias = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $materias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las materias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar materias (para autocomplete)
     */
    public function search(Request $request)
    {
        try {
            $termino = $request->get('q', '');

            $materias = Materia::buscar($termino)
                ->limit(10)
                ->get(['id', 'codigo_materia', 'nombre', 'sigla']);

            return response()->json([
                'success' => true,
                'data' => $materias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva materia
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo_materia' => 'required|string|max:20|unique:materias,codigo_materia',
                'nombre' => 'required|string|max:255',
                'sigla' => 'required|string|max:20',
                'horas_teoricas' => 'required|integer|min:0|max:10',
                'horas_practicas' => 'required|integer|min:0|max:10',
                'nivel' => 'required|integer|min:1|max:5',
                'semestre' => 'required|integer|min:1|max:10'
            ], [
                'codigo_materia.required' => 'El código de materia es obligatorio',
                'codigo_materia.unique' => 'El código de materia ya existe',
                'nombre.required' => 'El nombre de la materia es obligatorio',
                'sigla.required' => 'La sigla es obligatoria',
                'horas_teoricas.required' => 'Las horas teóricas son obligatorias',
                'horas_practicas.required' => 'Las horas prácticas son obligatorias',
                'nivel.required' => 'El nivel es obligatorio',
                'semestre.required' => 'El semestre es obligatorio'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $materia = Materia::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Materia creada exitosamente',
                'data' => $materia
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la materia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una materia específica
     */
    public function show($id)
    {
        try {
            $materia = Materia::with(['grupos.gestion', 'grupos.horarios'])
                ->findOrFail($id);

            // Calcular estadísticas
            $stats = [
                'total_grupos' => $materia->grupos()->count(),
                'grupos_activos' => $materia->grupos()->whereHas('gestion', function($q) {
                    $q->where('activa', true);
                })->count(),
                'horas_totales' => $materia->horas_totales
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'materia' => $materia,
                    'estadisticas' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Materia no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar materia
     */
    public function update(Request $request, $id)
    {
        try {
            $materia = Materia::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'codigo_materia' => 'required|string|max:20|unique:materias,codigo_materia,' . $id,
                'nombre' => 'required|string|max:255',
                'sigla' => 'required|string|max:20',
                'horas_teoricas' => 'required|integer|min:0|max:10',
                'horas_practicas' => 'required|integer|min:0|max:10',
                'nivel' => 'required|integer|min:1|max:5',
                'semestre' => 'required|integer|min:1|max:10'
            ], [
                'codigo_materia.unique' => 'El código de materia ya existe'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $materia->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Materia actualizada exitosamente',
                'data' => $materia
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la materia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar materia
     */
    public function destroy($id)
    {
        try {
            $materia = Materia::findOrFail($id);

            // Verificar si tiene grupos asignados
            if ($materia->grupos()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la materia porque tiene grupos asignados'
                ], 400);
            }

            $materia->delete();

            return response()->json([
                'success' => true,
                'message' => 'Materia eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la materia',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

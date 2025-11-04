<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AulaController extends Controller
{
    /**
     * Listar todas las aulas con filtros opcionales
     */
    public function index(Request $request)
    {
        try {
            $query = Aula::query();

            // Filtro por búsqueda
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('codigo_aula', 'like', "%{$search}%")
                      ->orWhere('nombre', 'like', "%{$search}%")
                      ->orWhere('edificio', 'like', "%{$search}%");
                });
            }

            // Filtro por tipo
            if ($request->has('tipo')) {
                $query->porTipo($request->tipo);
            }

            // Filtro por edificio
            if ($request->has('edificio')) {
                $query->porEdificio($request->edificio);
            }

            // Filtro por estado activo
            if ($request->has('activa')) {
                $query->where('activa', $request->activa === 'true' || $request->activa === '1');
            } else {
                $query->activas();
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'codigo_aula');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $aulas = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $aulas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las aulas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener aulas disponibles para un horario específico
     */
    public function disponibles(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'dia_semana' => 'required|integer|min:1|max:6',
                'hora_inicio' => 'required|date_format:H:i',
                'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
                'horario_id' => 'nullable|integer|exists:horarios,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $aulas = Aula::activas()->get()->filter(function($aula) use ($request) {
                return $aula->estaDisponible(
                    $request->dia_semana,
                    $request->hora_inicio,
                    $request->hora_fin,
                    $request->horario_id
                );
            })->values();

            return response()->json([
                'success' => true,
                'data' => $aulas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar disponibilidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva aula
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo_aula' => 'required|string|max:20|unique:aulas,codigo_aula',
                'nombre' => 'required|string|max:255',
                'capacidad' => 'required|integer|min:1|max:500',
                'edificio' => 'required|string|max:50',
                'piso' => 'required|integer|min:1|max:10',
                'tipo' => 'required|in:aula,laboratorio,auditorio',
                'activa' => 'boolean'
            ], [
                'codigo_aula.required' => 'El código de aula es obligatorio',
                'codigo_aula.unique' => 'El código de aula ya existe',
                'nombre.required' => 'El nombre es obligatorio',
                'capacidad.required' => 'La capacidad es obligatoria',
                'capacidad.min' => 'La capacidad mínima es 1',
                'tipo.in' => 'El tipo debe ser: aula, laboratorio o auditorio'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $aula = Aula::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Aula creada exitosamente',
                'data' => $aula
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un aula específica
     */
    public function show($id)
    {
        try {
            $aula = Aula::with(['horarios.grupo.materia', 'horarios.docente.user'])
                ->findOrFail($id);

            // Obtener ocupación por día
            $ocupacion = [];
            for ($dia = 1; $dia <= 6; $dia++) {
                $ocupacion[$dia] = $aula->obtenerOcupacionPorDia($dia);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'aula' => $aula,
                    'ocupacion_semanal' => $ocupacion
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Aula no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Obtener la ocupación de un aula
     */
    public function ocupacion($id, Request $request)
    {
        try {
            $aula = Aula::findOrFail($id);
            $dia = $request->get('dia', null);

            if ($dia) {
                $ocupacion = $aula->obtenerOcupacionPorDia($dia);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'dia' => $dia,
                        'horarios' => $ocupacion
                    ]
                ]);
            } else {
                // Ocupación de toda la semana
                $ocupacionSemanal = [];
                for ($d = 1; $d <= 6; $d++) {
                    $ocupacionSemanal[$d] = $aula->obtenerOcupacionPorDia($d);
                }
                return response()->json([
                    'success' => true,
                    'data' => $ocupacionSemanal
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la ocupación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar aula
     */
    public function update(Request $request, $id)
    {
        try {
            $aula = Aula::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'codigo_aula' => 'required|string|max:20|unique:aulas,codigo_aula,' . $id,
                'nombre' => 'required|string|max:255',
                'capacidad' => 'required|integer|min:1|max:500',
                'edificio' => 'required|string|max:50',
                'piso' => 'required|integer|min:1|max:10',
                'tipo' => 'required|in:aula,laboratorio,auditorio',
                'activa' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $aula->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Aula actualizada exitosamente',
                'data' => $aula
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar aula
     */
    public function destroy($id)
    {
        try {
            $aula = Aula::findOrFail($id);

            // Verificar si tiene horarios asignados
            if ($aula->horarios()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el aula porque tiene horarios asignados'
                ], 400);
            }

            $aula->delete();

            return response()->json([
                'success' => true,
                'message' => 'Aula eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

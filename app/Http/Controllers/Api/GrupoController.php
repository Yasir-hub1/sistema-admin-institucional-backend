<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\GestionAcademica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GrupoController extends Controller
{
    /**
     * Listar todos los grupos con filtros opcionales
     */
    public function index(Request $request)
    {
        try {
            $query = Grupo::with(['materia', 'gestion']);

            // Filtro por búsqueda
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('materia', function($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo_materia', 'like', "%{$search}%")
                      ->orWhere('sigla', 'like', "%{$search}%");
                });
            }

            // Filtro por materia
            if ($request->has('materia_id')) {
                $query->porMateria($request->materia_id);
            }

            // Filtro por gestión
            if ($request->has('gestion_id')) {
                $query->porGestion($request->gestion_id);
            } else {
                // Por defecto, mostrar solo de la gestión activa
                $query->deGestionActiva();
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $grupos = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $grupos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener grupos por gestión académica
     */
    public function porGestion($gestionId)
    {
        try {
            $gestion = GestionAcademica::findOrFail($gestionId);

            $grupos = Grupo::with(['materia', 'horarios.docente.user', 'horarios.aula'])
                ->where('gestion_id', $gestionId)
                ->orderBy('materia_id')
                ->orderBy('numero_grupo')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'gestion' => $gestion,
                    'grupos' => $grupos
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los grupos de la gestión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo grupo
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'materia_id' => 'required|exists:materias,id',
                'gestion_id' => 'required|exists:gestiones_academicas,id',
                'numero_grupo' => 'required|integer|min:1',
                'cupo_maximo' => 'required|integer|min:1|max:100'
            ], [
                'materia_id.required' => 'La materia es obligatoria',
                'materia_id.exists' => 'La materia seleccionada no existe',
                'gestion_id.required' => 'La gestión académica es obligatoria',
                'gestion_id.exists' => 'La gestión académica no existe',
                'numero_grupo.required' => 'El número de grupo es obligatorio',
                'cupo_maximo.required' => 'El cupo máximo es obligatorio'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que no exista un grupo con la misma materia, gestión y número
            $existente = Grupo::where('materia_id', $request->materia_id)
                ->where('gestion_id', $request->gestion_id)
                ->where('numero_grupo', $request->numero_grupo)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un grupo con este número para esta materia y gestión'
                ], 422);
            }

            $grupo = Grupo::create($request->all());
            $grupo->load(['materia', 'gestion']);

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado exitosamente',
                'data' => $grupo
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un grupo específico
     */
    public function show($id)
    {
        try {
            $grupo = Grupo::with([
                'materia',
                'gestion',
                'horarios.docente.user',
                'horarios.aula'
            ])->findOrFail($id);

            // Calcular carga horaria
            $cargaHoraria = $grupo->obtenerCargaHoraria();

            return response()->json([
                'success' => true,
                'data' => [
                    'grupo' => $grupo,
                    'carga_horaria' => $cargaHoraria,
                    'nombre_completo' => $grupo->nombre_completo
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar grupo
     */
    public function update(Request $request, $id)
    {
        try {
            $grupo = Grupo::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'materia_id' => 'required|exists:materias,id',
                'gestion_id' => 'required|exists:gestiones_academicas,id',
                'numero_grupo' => 'required|integer|min:1',
                'cupo_maximo' => 'required|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar unicidad (excluyendo el grupo actual)
            $existente = Grupo::where('materia_id', $request->materia_id)
                ->where('gestion_id', $request->gestion_id)
                ->where('numero_grupo', $request->numero_grupo)
                ->where('id', '!=', $id)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un grupo con este número para esta materia y gestión'
                ], 422);
            }

            $grupo->update($request->all());
            $grupo->load(['materia', 'gestion']);

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado exitosamente',
                'data' => $grupo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar grupo
     */
    public function destroy($id)
    {
        try {
            $grupo = Grupo::findOrFail($id);

            // Verificar si tiene horarios asignados
            if ($grupo->horarios()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el grupo porque tiene horarios asignados'
                ], 400);
            }

            $grupo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

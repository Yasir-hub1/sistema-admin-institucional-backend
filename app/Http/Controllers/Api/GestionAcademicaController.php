<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GestionAcademica;
use App\Models\Notificacion;
use App\Models\Docente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GestionAcademicaController extends Controller
{
    /**
     * Listar todas las gestiones académicas
     */
    public function index(Request $request)
    {
        try {
            Log::info('GestionAcademicaController@index - Iniciando consulta de gestiones', [
                'user_id' => $request->user()?->id,
                'filters' => $request->all()
            ]);

            $query = GestionAcademica::query();

            // Filtro por búsqueda
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('año', 'like', "%{$search}%");
                });
            }

            // Filtro por año
            if ($request->has('año') && !empty($request->año)) {
                $query->porAnio($request->año);
            }

            // Filtro por periodo
            if ($request->has('periodo') && !empty($request->periodo)) {
                $query->where('periodo', $request->periodo);
            }

            // Filtro por estado activo
            if ($request->has('activa') && $request->activa !== '') {
                if ($request->activa === 'true' || $request->activa === '1') {
                    $query->activas();
                } else {
                    $query->where('activa', false);
                }
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'año');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $gestiones = $query->paginate($perPage);

            Log::info('GestionAcademicaController@index - Gestiones encontradas', [
                'total' => $gestiones->total(),
                'count' => $gestiones->count(),
                'current_page' => $gestiones->currentPage(),
                'last_page' => $gestiones->lastPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => $gestiones
            ]);
        } catch (\Exception $e) {
            Log::error('GestionAcademicaController@index - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las gestiones académicas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener gestión activa actual
     */
    public function activa()
    {
        try {
            $gestion = GestionAcademica::getActiva();

            if (!$gestion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay ninguna gestión académica activa'
                ], 404);
            }

            $gestion->load('grupos.materia');

            return response()->json([
                'success' => true,
                'data' => $gestion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la gestión activa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva gestión académica
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255|unique:gestiones_academicas,nombre',
                'año' => 'required|integer|min:2000|max:2100',
                'periodo' => 'required|integer|in:1,2',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after:fecha_inicio',
                'activa' => 'boolean'
            ], [
                'nombre.required' => 'El nombre es obligatorio',
                'nombre.unique' => 'Ya existe una gestión con este nombre',
                'año.required' => 'El año es obligatorio',
                'periodo.required' => 'El periodo es obligatorio',
                'periodo.in' => 'El periodo debe ser 1 o 2',
                'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
                'fecha_fin.required' => 'La fecha de fin es obligatoria',
                'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Si se marca como activa, desactivar las demás
            if ($request->has('activa') && $request->activa) {
                GestionAcademica::where('activa', true)->update(['activa' => false]);
            }

            $gestion = GestionAcademica::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Gestión académica creada exitosamente',
                'data' => $gestion
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la gestión académica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una gestión académica específica
     */
    public function show($id)
    {
        try {
            $gestion = GestionAcademica::with(['grupos.materia', 'grupos.horarios.docente.user'])
                ->findOrFail($id);

            // Obtener estadísticas
            $estadisticas = $gestion->obtenerEstadisticas();

            return response()->json([
                'success' => true,
                'data' => [
                    'gestion' => $gestion,
                    'estadisticas' => $estadisticas,
                    'esta_en_curso' => $gestion->estaEnCurso()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gestión académica no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar gestión académica
     */
    public function update(Request $request, $id)
    {
        try {
            $gestion = GestionAcademica::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|required|string|max:255|unique:gestiones_academicas,nombre,' . $id,
                'año' => 'sometimes|required|integer|min:2000|max:2100',
                'periodo' => 'sometimes|required|integer|in:1,2',
                'fecha_inicio' => 'sometimes|required|date',
                'fecha_fin' => 'sometimes|required|date|after:fecha_inicio',
                'activa' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Si se marca como activa, desactivar las demás
            if ($request->has('activa') && $request->activa && !$gestion->activa) {
                GestionAcademica::where('activa', true)
                    ->where('id', '!=', $id)
                    ->update(['activa' => false]);
            }

            $gestion->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Gestión académica actualizada exitosamente',
                'data' => $gestion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la gestión académica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar gestión académica
     */
    public function destroy($id)
    {
        try {
            $gestion = GestionAcademica::findOrFail($id);

            // Verificar si tiene grupos asignados
            if ($gestion->grupos()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la gestión académica porque tiene grupos asignados'
                ], 400);
            }

            $gestion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Gestión académica eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la gestión académica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar una gestión académica
     */
    public function activar($id)
    {
        try {
            $gestion = GestionAcademica::findOrFail($id);

            // Desactivar todas las demás
            GestionAcademica::where('activa', true)
                ->where('id', '!=', $id)
                ->update(['activa' => false]);

            // Activar esta
            $gestion->update(['activa' => true]);

            // Notificar a todos los docentes sobre la nueva gestión activa
            $docentes = Docente::with('user')->whereHas('user', function($q) {
                $q->where('activo', true);
            })->get();

            foreach ($docentes as $docente) {
                if ($docente->user_id) {
                    Notificacion::crear(
                        'alerta',
                        'Nueva gestión académica activa',
                        "La gestión académica {$gestion->nombre} ({$gestion->año} - Periodo {$gestion->periodo}) ha sido activada. Revisa tus horarios asignados.",
                        $docente->user_id,
                        "/horarios?gestion_id={$gestion->id}"
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Gestión académica activada exitosamente',
                'data' => $gestion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al activar la gestión académica',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


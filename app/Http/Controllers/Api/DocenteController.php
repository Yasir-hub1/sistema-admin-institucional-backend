<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class DocenteController extends Controller
{
    /**
     * Listar docentes
     */
    public function index(Request $request)
    {
        $query = Docente::with(['user.rol']);

        // Filtros
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('codigo_docente', 'like', "%{$search}%");
        }

        if ($request->has('especialidad')) {
            $query->where('especialidad', $request->especialidad);
        }

        if ($request->has('grado_academico')) {
            $query->where('grado_academico', $request->grado_academico);
        }

        if ($request->has('activo')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('activo', $request->activo);
            });
        }

        $docentes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $docentes
        ]);
    }

    /**
     * Crear docente
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'codigo_docente' => 'required|string|unique:docentes,codigo_docente',
            'especialidad' => 'nullable|string|max:255',
            'grado_academico' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Obtener rol de docente
        $rolDocente = Role::where('nombre', 'docente')->first();
        if (!$rolDocente) {
            return response()->json([
                'success' => false,
                'message' => 'Rol de docente no encontrado'
            ], 500);
        }

        // Crear usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol_id' => $rolDocente->id,
            'activo' => true
        ]);

        // Crear docente
        $docente = Docente::create([
            'user_id' => $user->id,
            'codigo_docente' => $request->codigo_docente,
            'especialidad' => $request->especialidad,
            'grado_academico' => $request->grado_academico,
            'telefono' => $request->telefono,
            'direccion' => $request->direccion
        ]);

        $docente->load('user.rol');

        return response()->json([
            'success' => true,
            'message' => 'Docente creado exitosamente',
            'data' => $docente
        ], 201);
    }

    /**
     * Mostrar docente específico
     */
    public function show($id)
    {
        $docente = Docente::with(['user.rol', 'horarios.grupo.materia', 'horarios.aula'])
                          ->find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $docente
        ]);
    }

    /**
     * Actualizar docente
     */
    public function update(Request $request, $id)
    {
        $docente = Docente::find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'apellido_paterno' => 'sometimes|required|string|max:255',
            'apellido_materno' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $docente->user_id,
            'codigo_docente' => 'sometimes|required|string|unique:docentes,codigo_docente,' . $id,
            'especialidad' => 'nullable|string|max:255',
            'grado_academico' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:500',
            'activo' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Actualizar datos del usuario
        $userData = $request->only(['nombre', 'apellido_paterno', 'apellido_materno', 'email', 'activo']);
        if (!empty($userData)) {
            $docente->user->update($userData);
        }

        // Actualizar datos del docente
        $docenteData = $request->only(['codigo_docente', 'especialidad', 'grado_academico', 'telefono', 'direccion']);
        if (!empty($docenteData)) {
            $docente->update($docenteData);
        }

        $docente->load('user.rol');

        return response()->json([
            'success' => true,
            'message' => 'Docente actualizado exitosamente',
            'data' => $docente
        ]);
    }

    /**
     * Eliminar docente
     */
    public function destroy($id)
    {
        $docente = Docente::find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        // Verificar si tiene horarios asignados
        if ($docente->horarios()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el docente porque tiene horarios asignados'
            ], 400);
        }

        // Eliminar usuario asociado
        $docente->user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Docente eliminado exitosamente'
        ]);
    }

    /**
     * Obtener carga horaria del docente
     */
    public function cargaHoraria($id, Request $request)
    {
        $docente = Docente::find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        $gestionId = $request->get('gestion_id');
        $cargaHoraria = $docente->calcularCargaHoraria($gestionId);

        $horarios = $docente->horarios()
            ->with(['grupo.materia', 'aula'])
            ->when($gestionId, function ($query) use ($gestionId) {
                $query->whereHas('grupo', function ($q) use ($gestionId) {
                    $q->where('gestion_id', $gestionId);
                });
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'docente' => $docente->user->nombre_completo,
                'carga_horaria_total' => $cargaHoraria,
                'horarios' => $horarios
            ]
        ]);
    }

    /**
     * Obtener asistencias del docente
     */
    public function asistencias($id, Request $request)
    {
        $docente = Docente::find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        $query = $docente->asistencias()
            ->with(['horario.grupo.materia', 'horario.aula']);

        if ($request->has('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $asistencias = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $asistencias
        ]);
    }

    /**
     * Obtener estadísticas del docente
     */
    public function estadisticas($id, Request $request)
    {
        $docente = Docente::find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        $fechaInicio = $request->get('fecha_inicio');
        $fechaFin = $request->get('fecha_fin');

        $estadisticas = $docente->obtenerEstadisticasAsistencia($fechaInicio, $fechaFin);

        return response()->json([
            'success' => true,
            'data' => [
                'docente' => $docente->user->nombre_completo,
                'estadisticas' => $estadisticas
            ]
        ]);
    }
}

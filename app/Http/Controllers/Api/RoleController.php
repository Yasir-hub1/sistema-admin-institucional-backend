<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Listar todos los roles
     */
    public function index(Request $request)
    {
        try {
            $query = Role::with('permisos')->withCount(['users', 'permisos']);

            // Filtro por búsqueda
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'nombre');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $roles = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los roles sin paginación (para selects)
     */
    public function all()
    {
        try {
            $roles = Role::orderBy('nombre')->get();

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo rol
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:50|unique:roles,nombre',
                'descripcion' => 'nullable|string|max:255'
            ], [
                'nombre.required' => 'El nombre es obligatorio',
                'nombre.unique' => 'Ya existe un rol con este nombre',
                'nombre.max' => 'El nombre no puede exceder 50 caracteres'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $role = Role::create($request->only(['nombre', 'descripcion']));

            // Asignar permisos si vienen en el request
            if ($request->has('permisos') && is_array($request->permisos)) {
                $role->asignarPermisos($request->permisos);
            }

            $role->load('permisos');

            return response()->json([
                'success' => true,
                'message' => 'Rol creado exitosamente',
                'data' => $role
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un rol específico
     */
    public function show($id)
    {
        try {
            $role = Role::with(['users' => function($query) {
                $query->limit(10);
            }])->with('permisos')->withCount(['users', 'permisos'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar rol
     */
    public function update(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|required|string|max:50|unique:roles,nombre,' . $id,
                'descripcion' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $role->update($request->only(['nombre', 'descripcion']));

            // Actualizar permisos si vienen en el request
            if ($request->has('permisos') && is_array($request->permisos)) {
                $role->asignarPermisos($request->permisos);
            }

            $role->load('permisos');

            return response()->json([
                'success' => true,
                'message' => 'Rol actualizado exitosamente',
                'data' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar rol
     */
    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);

            // Verificar si tiene usuarios asignados
            if ($role->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el rol porque tiene usuarios asignados'
                ], 400);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rol eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los permisos disponibles
     */
    public function getPermisos()
    {
        try {
            $permisos = Permiso::orderBy('modulo')->orderBy('accion')->get();

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
     * Asignar permisos a un rol
     */
    public function asignarPermisos(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'permisos' => 'required|array',
                'permisos.*' => 'exists:permisos,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $role->asignarPermisos($request->permisos);
            $role->load('permisos');

            return response()->json([
                'success' => true,
                'message' => 'Permisos asignados exitosamente',
                'data' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar permisos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


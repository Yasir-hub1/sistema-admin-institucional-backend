<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Docente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios
     */
    public function index(Request $request)
    {
        try {
            Log::info('UserController@index - Iniciando consulta de usuarios', [
                'user_id' => $request->user()?->id,
                'filters' => $request->all()
            ]);

            $query = User::with('rol');

            // Filtro por búsqueda - solo si tiene valor
            if ($request->filled('search')) {
                $search = trim($request->get('search'));
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            }

            // Filtro por rol - solo si tiene valor
            if ($request->filled('rol_id')) {
                $rolId = $request->get('rol_id');
                if (!empty($rolId)) {
                    $query->where('rol_id', $rolId);
                }
            }

            // Filtro por estado activo - solo si tiene valor
            if ($request->filled('activo')) {
                $activo = $request->get('activo');
                if ($activo !== '') {
                    $query->where('activo', $activo === 'true' || $activo === '1' || $activo === 1);
                }
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            Log::info('UserController@index - Usuarios encontrados', [
                'total' => $users->total(),
                'count' => $users->count(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('UserController@index - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo usuario
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'rol_id' => 'required|exists:roles,id',
                'activo' => 'boolean'
            ], [
                'name.required' => 'El nombre es obligatorio',
                'email.required' => 'El email es obligatorio',
                'email.email' => 'El email debe ser válido',
                'email.unique' => 'El email ya está registrado',
                'password.required' => 'La contraseña es obligatoria',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'rol_id.required' => 'El rol es obligatorio',
                'rol_id.exists' => 'El rol seleccionado no existe'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'rol_id' => $request->rol_id,
                'activo' => $request->activo ?? true
            ]);

            $user->load('rol');

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un usuario específico
     */
    public function show($id)
    {
        try {
            $user = User::with(['rol', 'docente'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'password' => 'sometimes|string|min:8',
                'rol_id' => 'sometimes|required|exists:roles,id',
                'activo' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only(['name', 'email', 'rol_id', 'activo']);

            if ($request->has('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);
            $user->load('rol');

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar usuario
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // No permitir eliminar el propio usuario (si aplica)
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminar tu propio usuario'
                ], 400);
            }

            // Verificar si tiene registros relacionados (docente, etc.)
            if ($user->docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el usuario porque tiene registros relacionados. Elimine primero el docente asociado.'
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado activo/inactivo del usuario
     */
    public function toggleStatus($id)
    {
        try {
            $user = User::findOrFail($id);

            // No permitir desactivar el propio usuario
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes desactivar tu propio usuario'
                ], 400);
            }

            $user->update(['activo' => !$user->activo]);
            $user->load('rol');

            return response()->json([
                'success' => true,
                'message' => $user->activo ? 'Usuario activado exitosamente' : 'Usuario desactivado exitosamente',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importar usuarios desde archivo Excel/CSV
     */
    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $data = Excel::toArray([], $file);

            if (empty($data) || empty($data[0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo está vacío o no tiene formato válido'
                ], 422);
            }

            $rows = $data[0];
            $header = array_shift($rows); // Primera fila como encabezados

            // Buscar índices de columnas
            $colMap = [];
            foreach ($header as $index => $col) {
                $colName = strtolower(trim($col));
                if (strpos($colName, 'nombre') !== false || strpos($colName, 'name') !== false) {
                    $colMap['name'] = $index;
                } elseif (strpos($colName, 'email') !== false || strpos($colName, 'correo') !== false) {
                    $colMap['email'] = $index;
                } elseif (strpos($colName, 'rol') !== false || strpos($colName, 'role') !== false) {
                    $colMap['rol'] = $index;
                } elseif (strpos($colName, 'codigo') !== false || strpos($colName, 'código') !== false) {
                    $colMap['codigo_docente'] = $index;
                }
            }

            if (!isset($colMap['name']) || !isset($colMap['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo debe contener al menos las columnas: nombre/name y email'
                ], 422);
            }

            $creados = 0;
            $actualizados = 0;
            $errores = [];

            foreach ($rows as $rowIndex => $row) {
                try {
                    if (empty($row[$colMap['name']]) || empty($row[$colMap['email']])) {
                        $errores[] = "Fila " . ($rowIndex + 2) . ": Faltan datos obligatorios";
                        continue;
                    }

                    $email = trim($row[$colMap['email']]);
                    $name = trim($row[$colMap['name']]);

                    // Obtener o crear rol
                    $rolNombre = isset($colMap['rol']) && isset($row[$colMap['rol']])
                        ? trim($row[$colMap['rol']])
                        : 'docente';

                    $role = Role::where('nombre', $rolNombre)->first();
                    if (!$role) {
                        $role = Role::where('nombre', 'docente')->first();
                    }

                    if (!$role) {
                        $errores[] = "Fila " . ($rowIndex + 2) . ": Rol '{$rolNombre}' no encontrado";
                        continue;
                    }

                    // Buscar usuario existente
                    $user = User::where('email', $email)->first();

                    if ($user) {
                        // Actualizar usuario existente
                        $user->update([
                            'name' => $name,
                            'rol_id' => $role->id
                        ]);
                        $actualizados++;
                    } else {
                        // Crear nuevo usuario con password temporal
                        $passwordTemporal = Hash::make('temp123456'); // Password temporal, el usuario deberá cambiarlo
                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => $passwordTemporal,
                            'rol_id' => $role->id,
                            'activo' => true
                        ]);

                        // Si es docente, crear registro en docentes si hay código
                        if ($rolNombre === 'docente' && isset($colMap['codigo_docente']) && isset($row[$colMap['codigo_docente']])) {
                            $codigoDocente = trim($row[$colMap['codigo_docente']]);
                            if ($codigoDocente) {
                                Docente::firstOrCreate(
                                    ['codigo_docente' => $codigoDocente],
                                    ['user_id' => $user->id]
                                );
                            }
                        }

                        $creados++;
                    }
                } catch (\Exception $e) {
                    $errores[] = "Fila " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Importación completada: {$creados} creados, {$actualizados} actualizados",
                'data' => [
                    'creados' => $creados,
                    'actualizados' => $actualizados,
                    'errores' => $errores
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al importar usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login del usuario
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();
        $user->load('rol.permisos');

        if (!$user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Su cuenta está desactivada'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Obtener permisos del usuario
        $permisos = $user->obtenerPermisos()->map(function($permiso) {
            return [
                'id' => $permiso->id,
                'nombre' => $permiso->nombre,
                'modulo' => $permiso->modulo,
                'accion' => $permiso->accion,
                'descripcion' => $permiso->descripcion
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nombre_completo' => $user->nombre_completo,
                    'email' => $user->email,
                    'rol' => $user->rol->nombre ?? null,
                    'rol_id' => $user->rol_id,
                    'activo' => $user->activo,
                    'permisos' => $permisos
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Logout del usuario
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                // Eliminar TODOS los tokens del usuario (logout completo)
                $user->tokens()->delete();

                // Alternativa: eliminar solo el token actual
                // $request->user()->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ]);
        } catch (\Exception $e) {
            // Aún si hay error, retornar éxito para que el frontend limpie
            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ]);
        }
    }

    /**
     * Obtener datos del usuario autenticado
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('rol.permisos');

        // Obtener permisos del usuario
        $permisos = $user->obtenerPermisos()->map(function($permiso) {
            return [
                'id' => $permiso->id,
                'nombre' => $permiso->nombre,
                'modulo' => $permiso->modulo,
                'accion' => $permiso->accion,
                'descripcion' => $permiso->descripcion
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nombre_completo' => $user->nombre_completo,
                    'email' => $user->email,
                    'rol' => $user->rol->nombre ?? null,
                    'rol_id' => $user->rol_id,
                    'activo' => $user->activo,
                    'created_at' => $user->created_at,
                    'permisos' => $permisos
                ]
            ]
        ]);
    }

    /**
     * Refrescar token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        // Eliminar token actual
        $request->user()->currentAccessToken()->delete();

        // Crear nuevo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refrescado exitosamente',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }
}

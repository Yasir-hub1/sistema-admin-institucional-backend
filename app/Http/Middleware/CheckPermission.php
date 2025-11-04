<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        $user = $request->user();

        // Verificar si tiene el permiso
        if (!$user->tienePermiso($permiso)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción. Permiso requerido: ' . $permiso
            ], 403);
        }

        return $next($request);
    }
}


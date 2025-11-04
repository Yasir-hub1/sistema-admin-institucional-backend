<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        // Cargar la relación rol si no está cargada
        $user = $request->user();
        if (!$user->relationLoaded('rol')) {
            $user->load('rol');
        }

        $userRole = $user->rol->nombre ?? null;

        if (!$userRole || !in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción. Rol requerido: ' . implode(', ', $roles) . '. Tu rol: ' . ($userRole ?? 'sin rol')
            ], 403);
        }

        return $next($request);
    }
}

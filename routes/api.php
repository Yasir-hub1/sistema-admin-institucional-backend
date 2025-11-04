<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DocenteController;
use App\Http\Controllers\Api\MateriaController;
use App\Http\Controllers\Api\AulaController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\HorarioController;
use App\Http\Controllers\Api\AsistenciaController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GestionAcademicaController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\NotificacionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas de autenticación (públicas)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
});

// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {

    // Rutas de usuarios
    Route::prefix('usuarios')->middleware('role:admin,coordinador')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store'])->middleware('role:admin');
        Route::post('import', [UserController::class, 'import'])->middleware('role:admin');
        Route::get('{id}', [UserController::class, 'show']);
        Route::put('{id}', [UserController::class, 'update'])->middleware('role:admin');
        Route::delete('{id}', [UserController::class, 'destroy'])->middleware('role:admin');
        Route::put('{id}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('role:admin');
    });

    // Rutas de docentes
    Route::prefix('docentes')->group(function () {
        Route::get('/', [DocenteController::class, 'index']);
        Route::post('/', [DocenteController::class, 'store'])->middleware('role:admin,coordinador');
        Route::get('{id}', [DocenteController::class, 'show']);
        Route::put('{id}', [DocenteController::class, 'update'])->middleware('role:admin,coordinador');
        Route::delete('{id}', [DocenteController::class, 'destroy'])->middleware('role:admin');
        Route::get('{id}/carga-horaria', [DocenteController::class, 'cargaHoraria']);
        Route::get('{id}/asistencias', [DocenteController::class, 'asistencias']);
        Route::get('{id}/estadisticas', [DocenteController::class, 'estadisticas']);
    });

    // Rutas de materias
    Route::prefix('materias')->group(function () {
        Route::get('/', [MateriaController::class, 'index']);
        Route::post('/', [MateriaController::class, 'store'])->middleware('role:admin,coordinador');
        Route::get('search', [MateriaController::class, 'search']);
        Route::get('{id}', [MateriaController::class, 'show']);
        Route::put('{id}', [MateriaController::class, 'update'])->middleware('role:admin,coordinador');
        Route::delete('{id}', [MateriaController::class, 'destroy'])->middleware('role:admin');
    });

    // Rutas de aulas
    Route::prefix('aulas')->group(function () {
        Route::get('/', [AulaController::class, 'index']);
        Route::post('/', [AulaController::class, 'store'])->middleware('role:admin,coordinador');
        Route::get('disponibles', [AulaController::class, 'disponibles']);
        Route::get('{id}', [AulaController::class, 'show']);
        Route::get('{id}/ocupacion', [AulaController::class, 'ocupacion']);
        Route::put('{id}', [AulaController::class, 'update'])->middleware('role:admin,coordinador');
        Route::delete('{id}', [AulaController::class, 'destroy'])->middleware('role:admin');
    });

    // Rutas de grupos
    Route::prefix('grupos')->group(function () {
        Route::get('/', [GrupoController::class, 'index']);
        Route::post('/', [GrupoController::class, 'store'])->middleware('role:admin,coordinador');
        Route::get('gestion/{id}', [GrupoController::class, 'porGestion']);
        Route::get('{id}', [GrupoController::class, 'show']);
        Route::put('{id}', [GrupoController::class, 'update'])->middleware('role:admin,coordinador');
        Route::delete('{id}', [GrupoController::class, 'destroy'])->middleware('role:admin');
    });

    // Rutas de horarios
    Route::prefix('horarios')->group(function () {
        Route::get('/', [HorarioController::class, 'index']);
        Route::post('/', [HorarioController::class, 'store'])->middleware('role:admin,coordinador');
        Route::post('generar-automatico', [HorarioController::class, 'generarAutomatico'])->middleware('role:admin,coordinador');
        Route::post('validar', [HorarioController::class, 'validar'])->middleware('role:admin,coordinador');
        Route::get('semanal', [HorarioController::class, 'semanal']);
        Route::get('docente/{id}', [HorarioController::class, 'horariosDocente']);
        Route::get('grupo/{id}', [HorarioController::class, 'horariosGrupo']);
        Route::get('aula/{id}', [HorarioController::class, 'horariosAula']);
        Route::get('{id}', [HorarioController::class, 'show']);
        Route::put('{id}', [HorarioController::class, 'update'])->middleware('role:admin,coordinador');
        Route::delete('{id}', [HorarioController::class, 'destroy'])->middleware('role:admin');
    });

    // Rutas de asistencias
    Route::prefix('asistencias')->group(function () {
        Route::get('/', [AsistenciaController::class, 'index']);
        Route::post('/', [AsistenciaController::class, 'store']);
        Route::post('qr', [AsistenciaController::class, 'registrarConQR']);
        Route::post('geolocalizacion', [AsistenciaController::class, 'registrarConGeolocalizacion']);
        Route::get('docente/{id}', [AsistenciaController::class, 'porDocente']);
        Route::get('horario/{id}', [AsistenciaController::class, 'porHorario']);
        Route::get('generar-qr/{horario_id}', [AsistenciaController::class, 'generarQR']);
        Route::get('{id}', [AsistenciaController::class, 'show']);
        Route::put('{id}', [AsistenciaController::class, 'update']);
        Route::delete('{id}', [AsistenciaController::class, 'destroy'])->middleware('role:admin,coordinador');
    });

    // Rutas de gestiones académicas
    Route::prefix('gestiones-academicas')->group(function () {
        Route::get('/', [GestionAcademicaController::class, 'index']);
        Route::get('activa', [GestionAcademicaController::class, 'activa']);
        Route::post('/', [GestionAcademicaController::class, 'store'])->middleware('role:admin,coordinador');
        Route::get('{id}', [GestionAcademicaController::class, 'show']);
        Route::put('{id}', [GestionAcademicaController::class, 'update'])->middleware('role:admin,coordinador');
        Route::delete('{id}', [GestionAcademicaController::class, 'destroy'])->middleware('role:admin');
        Route::put('{id}/activar', [GestionAcademicaController::class, 'activar'])->middleware('role:admin,coordinador');
    });

    // Rutas de roles
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('all', [RoleController::class, 'all']);
        Route::get('permisos', [RoleController::class, 'getPermisos']);
        Route::post('/', [RoleController::class, 'store'])->middleware('role:admin');
        Route::get('{id}', [RoleController::class, 'show']);
        Route::put('{id}', [RoleController::class, 'update'])->middleware('role:admin');
        Route::put('{id}/permisos', [RoleController::class, 'asignarPermisos'])->middleware('role:admin');
        Route::delete('{id}', [RoleController::class, 'destroy'])->middleware('role:admin');
    });

    // Rutas de permisos
    Route::prefix('permisos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PermisoController::class, 'index']);
        Route::get('por-modulo', [\App\Http\Controllers\Api\PermisoController::class, 'porModulo']);
    });

    // Rutas de reportes
    Route::prefix('reportes')->group(function () {
        Route::get('horarios-semanal', [ReporteController::class, 'horariosSemanal']);
        Route::get('asistencia-docente', [ReporteController::class, 'asistenciaDocente']);
        Route::get('carga-horaria', [ReporteController::class, 'cargaHoraria']);
        Route::get('aulas-ocupacion', [ReporteController::class, 'aulasOcupacion']);
        Route::post('export-pdf', [ReporteController::class, 'exportPDF']);
        Route::post('export-excel', [ReporteController::class, 'exportExcel']);
    });

    // Rutas de notificaciones
    Route::prefix('notificaciones')->group(function () {
        Route::get('/', [NotificacionController::class, 'index']);
        Route::get('no-leidas', [NotificacionController::class, 'contarNoLeidas']);
        Route::put('marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas']);
        Route::put('{id}/marcar-leida', [NotificacionController::class, 'marcarLeida']);
        Route::delete('{id}', [NotificacionController::class, 'destroy']);
    });

    // Rutas de auditoría
    Route::prefix('auditoria')->middleware('role:admin,coordinador')->group(function () {
        Route::get('/', function (Request $request) {
            $query = \App\Models\Auditoria::with('user')
                ->orderBy('created_at', 'desc');

            if ($request->has('modelo')) {
                $query->porModelo($request->modelo);
            }

            if ($request->has('accion')) {
                $query->porAccion($request->accion);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $query->whereBetween('created_at', [$request->fecha_inicio, $request->fecha_fin]);
            }

            return response()->json([
                'success' => true,
                'data' => $query->paginate($request->get('per_page', 15))
            ]);
        });
        Route::get('modelo/{modelo}', function ($modelo, Request $request) {
            $auditoria = \App\Models\Auditoria::with('user')
                ->porModelo($modelo)
                ->when($request->has('modelo_id'), function ($q) use ($request) {
                    $q->where('modelo_id', $request->modelo_id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $auditoria
            ]);
        });
    });

    // Rutas de dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('estadisticas', [DashboardController::class, 'estadisticas']);
        Route::get('docente', [DashboardController::class, 'dashboardDocente'])->middleware('role:docente');
        Route::get('coordinador', [DashboardController::class, 'dashboardCoordinador'])->middleware('role:coordinador');
        Route::get('admin', [DashboardController::class, 'dashboardAdmin'])->middleware('role:admin');
    });

});

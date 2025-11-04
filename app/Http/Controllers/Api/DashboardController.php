<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Materia;
use App\Models\Aula;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Asistencia;
use App\Models\GestionAcademica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Estadísticas generales del sistema
     */
    public function estadisticas(Request $request)
    {
        try {
            $gestionActiva = GestionAcademica::where('activa', true)->first();

            $stats = [
                'totales' => [
                    'docentes' => Docente::activos()->count(),
                    'materias' => Materia::count(),
                    'aulas' => Aula::activas()->count(),
                    'grupos' => $gestionActiva ? Grupo::where('gestion_id', $gestionActiva->id)->count() : 0,
                ],
                'gestion_activa' => $gestionActiva,
            ];

            // Estadísticas de asistencias (últimos 30 días)
            $fechaInicio = now()->subDays(30);
            $asistencias = Asistencia::where('fecha', '>=', $fechaInicio)->get();

            $stats['asistencias'] = [
                'total' => $asistencias->count(),
                'presente' => $asistencias->where('estado', 'presente')->count(),
                'ausente' => $asistencias->where('estado', 'ausente')->count(),
                'tardanza' => $asistencias->where('estado', 'tardanza')->count(),
                'justificado' => $asistencias->where('estado', 'justificado')->count(),
            ];

            if ($stats['asistencias']['total'] > 0) {
                $stats['asistencias']['porcentaje_asistencia'] = round(
                    (($stats['asistencias']['presente'] + $stats['asistencias']['tardanza']) / $stats['asistencias']['total']) * 100,
                    2
                );
            } else {
                $stats['asistencias']['porcentaje_asistencia'] = 0;
            }

            // Actividad reciente
            $stats['actividad_reciente'] = Asistencia::with(['horario.grupo.materia', 'docente.user'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($asistencia) {
                    return [
                        'id' => $asistencia->id,
                        'tipo' => 'asistencia',
                        'mensaje' => "Asistencia registrada para {$asistencia->horario->grupo->materia->nombre}",
                        'docente' => $asistencia->docente->user->name,
                        'fecha' => $asistencia->created_at->diffForHumans(),
                    ];
                });

            // Próximas clases del día actual
            $hoy = now()->dayOfWeek;
            if ($hoy == 0) $hoy = 7; // Convertir domingo (0) a 7
            if ($hoy > 6) $hoy = 1; // Si es domingo, mostrar lunes

            $stats['proximas_clases'] = Horario::with(['grupo.materia', 'aula', 'docente.user'])
                ->where('dia_semana', $hoy)
                ->whereTime('hora_inicio', '>=', now()->format('H:i:s'))
                ->orderBy('hora_inicio')
                ->limit(5)
                ->get()
                ->map(function($horario) {
                    return [
                        'id' => $horario->id,
                        'materia' => $horario->grupo->materia->nombre,
                        'grupo' => $horario->grupo->numero_grupo,
                        'aula' => $horario->aula->codigo_aula,
                        'docente' => $horario->docente->user->name,
                        'hora_inicio' => Carbon::parse($horario->hora_inicio)->format('H:i'),
                        'hora_fin' => Carbon::parse($horario->hora_fin)->format('H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard para docentes
     */
    public function dashboardDocente(Request $request)
    {
        try {
            $user = $request->user();
            $docente = Docente::where('user_id', $user->id)->firstOrFail();

            // Horarios del docente
            $horarios = Horario::with(['grupo.materia', 'aula'])
                ->where('docente_id', $docente->id)
                ->get()
                ->groupBy('dia_semana');

            // Asistencias del mes actual
            $mesActual = now()->format('Y-m');
            $asistencias = Asistencia::where('docente_id', $docente->id)
                ->whereRaw("DATE_FORMAT(fecha, '%Y-%m') = ?", [$mesActual])
                ->get();

            $stats = [
                'asistencias_mes' => [
                    'total' => $asistencias->count(),
                    'presente' => $asistencias->where('estado', 'presente')->count(),
                    'tardanza' => $asistencias->where('estado', 'tardanza')->count(),
                    'ausente' => $asistencias->where('estado', 'ausente')->count(),
                ],
                'horarios' => $horarios,
                'carga_horaria' => $docente->calcularCargaHoraria(GestionAcademica::where('activa', true)->first()?->id),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el dashboard del docente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard para coordinador
     */
    public function dashboardCoordinador(Request $request)
    {
        try {
            $gestionActiva = GestionAcademica::where('activa', true)->first();

            $stats = [
                'resumen' => [
                    'docentes' => Docente::activos()->count(),
                    'materias' => Materia::count(),
                    'grupos' => $gestionActiva ? Grupo::where('gestion_id', $gestionActiva->id)->count() : 0,
                    'horarios' => $gestionActiva ? Horario::whereHas('grupo', function($q) use ($gestionActiva) {
                        $q->where('gestion_id', $gestionActiva->id);
                    })->count() : 0,
                ],
                'asistencias_hoy' => Asistencia::whereDate('fecha', now())->count(),
                'pendientes' => [
                    'grupos_sin_horario' => $gestionActiva ? Grupo::where('gestion_id', $gestionActiva->id)
                        ->doesntHave('horarios')
                        ->count() : 0,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el dashboard del coordinador',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard para administrador
     */
    public function dashboardAdmin(Request $request)
    {
        try {
            return $this->estadisticas($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el dashboard del administrador',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

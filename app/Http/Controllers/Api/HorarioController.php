<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use App\Models\Docente;
use App\Models\Aula;
use App\Models\Grupo;
use App\Models\Notificacion;
use App\Models\GestionAcademica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HorarioController extends Controller
{
    /**
     * Listar horarios con filtros
     */
    public function index(Request $request)
    {
        $query = Horario::with(['grupo.materia', 'docente.user', 'aula']);

        // Filtros
        if ($request->has('docente_id')) {
            $query->where('docente_id', $request->docente_id);
        }

        if ($request->has('aula_id')) {
            $query->where('aula_id', $request->aula_id);
        }

        if ($request->has('grupo_id')) {
            $query->where('grupo_id', $request->grupo_id);
        }

        if ($request->has('dia_semana')) {
            $query->where('dia_semana', $request->dia_semana);
        }

        if ($request->has('gestion_id')) {
            $query->whereHas('grupo', function ($q) use ($request) {
                $q->where('gestion_id', $request->gestion_id);
            });
        }

        if ($request->has('materia_id')) {
            $query->whereHas('grupo', function ($q) use ($request) {
                $q->where('materia_id', $request->materia_id);
            });
        }

        $horarios = $query->orderBy('dia_semana')
                         ->orderBy('hora_inicio')
                         ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    /**
     * Crear horario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'required|exists:grupos,id',
            'docente_id' => 'required|exists:docentes,id',
            'aula_id' => 'required|exists:aulas,id',
            'dia_semana' => 'required|integer|between:1,6',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validar conflictos
        $conflictos = $this->validarConflictos($request->all());
        if (!empty($conflictos)) {
            return response()->json([
                'success' => false,
                'message' => 'Se encontraron conflictos de horario',
                'conflictos' => $conflictos
            ], 422);
        }

        // Validar carga horaria máxima del docente
        $docente = Docente::find($request->docente_id);
        $grupo = Grupo::find($request->grupo_id);
        $gestionId = $grupo->gestion_id ?? null;

        // Calcular nueva carga si se crea este horario
        $horarioTemporal = new Horario($request->all());
        $cargaActual = $docente->calcularCargaHoraria($gestionId);
        $inicio = \Carbon\Carbon::parse($request->hora_inicio);
        $fin = \Carbon\Carbon::parse($request->hora_fin);
        $nuevasHoras = $inicio->diffInMinutes($fin) / 60;
        $cargaTotal = $cargaActual + $nuevasHoras;
        $cargaMaxima = $docente->carga_horaria_maxima ?? 40;

        if ($cargaTotal > $cargaMaxima) {
            return response()->json([
                'success' => false,
                'message' => "El docente excedería su carga horaria máxima. Carga actual: {$cargaActual}h, Nueva carga: {$cargaTotal}h, Máxima permitida: {$cargaMaxima}h",
                'carga_actual' => $cargaActual,
                'nueva_carga' => $cargaTotal,
                'carga_maxima' => $cargaMaxima
            ], 422);
        }

        // Validar disponibilidad del docente
        if (!$docente->estaDisponible($request->dia_semana, $request->hora_inicio, $request->hora_fin)) {
            return response()->json([
                'success' => false,
                'message' => 'El docente no está disponible en este horario según su configuración de disponibilidad'
            ], 422);
        }

        $horario = Horario::create($request->all());
        $horario->load(['grupo.materia', 'docente.user', 'aula']);

        // Crear notificación para el docente
        if ($docente->user_id) {
            Notificacion::crear(
                'horario_cambio',
                'Nuevo horario asignado',
                "Se te ha asignado un nuevo horario: {$horario->grupo->materia->nombre} - Grupo {$horario->grupo->numero_grupo} el " . $this->obtenerNombreDia($request->dia_semana) . " de {$request->hora_inicio} a {$request->hora_fin} en {$horario->aula->nombre}",
                $docente->user_id,
                "/horarios/{$horario->id}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Horario creado exitosamente',
            'data' => $horario
        ], 201);
    }

    /**
     * Mostrar horario específico
     */
    public function show($id)
    {
        $horario = Horario::with(['grupo.materia', 'docente.user', 'aula'])
                          ->find($id);

        if (!$horario) {
            return response()->json([
                'success' => false,
                'message' => 'Horario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $horario
        ]);
    }

    /**
     * Actualizar horario
     */
    public function update(Request $request, $id)
    {
        $horario = Horario::find($id);

        if (!$horario) {
            return response()->json([
                'success' => false,
                'message' => 'Horario no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'grupo_id' => 'sometimes|required|exists:grupos,id',
            'docente_id' => 'sometimes|required|exists:docentes,id',
            'aula_id' => 'sometimes|required|exists:aulas,id',
            'dia_semana' => 'sometimes|required|integer|between:1,6',
            'hora_inicio' => 'sometimes|required|date_format:H:i',
            'hora_fin' => 'sometimes|required|date_format:H:i|after:hora_inicio'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validar conflictos excluyendo el horario actual
        $datos = array_merge($horario->toArray(), $request->all());
        $conflictos = $this->validarConflictos($datos, $id);
        if (!empty($conflictos)) {
            return response()->json([
                'success' => false,
                'message' => 'Se encontraron conflictos de horario',
                'conflictos' => $conflictos
            ], 422);
        }

        // Validar carga horaria si se cambia docente u horario
        $docenteId = $request->get('docente_id', $horario->docente_id);
        $grupoId = $request->get('grupo_id', $horario->grupo_id);

        if ($request->has('docente_id') || $request->has('hora_inicio') || $request->has('hora_fin')) {
            $docente = Docente::find($docenteId);
            $grupo = Grupo::find($grupoId);
            $gestionId = $grupo->gestion_id ?? null;

            // Recalcular carga sin el horario actual
            $cargaSinEste = $docente->horarios()
                ->where('id', '!=', $id)
                ->when($gestionId, function ($q) use ($gestionId) {
                    $q->whereHas('grupo', function ($q2) use ($gestionId) {
                        $q2->where('gestion_id', $gestionId);
                    });
                })
                ->get()
                ->sum(function ($h) {
                    $inicio = \Carbon\Carbon::parse($h->hora_inicio);
                    $fin = \Carbon\Carbon::parse($h->hora_fin);
                    return $inicio->diffInMinutes($fin) / 60;
                });

            $horaInicio = $request->get('hora_inicio', $horario->hora_inicio);
            $horaFin = $request->get('hora_fin', $horario->hora_fin);
            $inicio = \Carbon\Carbon::parse($horaInicio);
            $fin = \Carbon\Carbon::parse($horaFin);
            $horasEsteHorario = $inicio->diffInMinutes($fin) / 60;
            $cargaTotal = $cargaSinEste + $horasEsteHorario;
            $cargaMaxima = $docente->carga_horaria_maxima ?? 40;

            if ($cargaTotal > $cargaMaxima) {
                return response()->json([
                    'success' => false,
                    'message' => "El docente excedería su carga horaria máxima. Carga: {$cargaTotal}h, Máxima: {$cargaMaxima}h",
                    'carga_total' => $cargaTotal,
                    'carga_maxima' => $cargaMaxima
                ], 422);
            }
        }

        // Guardar datos anteriores para notificación
        $datosAnteriores = $horario->toArray();

        $horario->update($request->all());
        $horario->load(['grupo.materia', 'docente.user', 'aula']);

        // Crear notificación si hay cambios significativos
        $docente = Docente::find($docenteId);
        if ($docente && $docente->user_id && ($request->has('docente_id') || $request->has('hora_inicio') || $request->has('hora_fin') || $request->has('aula_id'))) {
            Notificacion::crear(
                'horario_cambio',
                'Horario modificado',
                "Se ha modificado tu horario de {$horario->grupo->materia->nombre} - Grupo {$horario->grupo->numero_grupo}",
                $docente->user_id,
                "/horarios/{$horario->id}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Horario actualizado exitosamente',
            'data' => $horario
        ]);
    }

    /**
     * Eliminar horario
     */
    public function destroy($id)
    {
        $horario = Horario::find($id);

        if (!$horario) {
            return response()->json([
                'success' => false,
                'message' => 'Horario no encontrado'
            ], 404);
        }

        // Verificar si tiene asistencias registradas
        if ($horario->asistencias()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el horario porque tiene asistencias registradas'
            ], 400);
        }

        // Crear notificación antes de eliminar
        $docente = $horario->docente;
        if ($docente && $docente->user_id) {
            Notificacion::crear(
                'horario_cambio',
                'Horario eliminado',
                "Se ha eliminado tu horario de {$horario->grupo->materia->nombre} - Grupo {$horario->grupo->numero_grupo}",
                $docente->user_id,
                "/horarios"
            );
        }

        $horario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Horario eliminado exitosamente'
        ]);
    }

    /**
     * Validar horario antes de guardar
     */
    public function validar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'required|exists:grupos,id',
            'docente_id' => 'required|exists:docentes,id',
            'aula_id' => 'required|exists:aulas,id',
            'dia_semana' => 'required|integer|between:1,6',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $conflictos = $this->validarConflictos($request->all());

        return response()->json([
            'success' => true,
            'data' => [
                'valido' => empty($conflictos),
                'conflictos' => $conflictos
            ]
        ]);
    }

    /**
     * Obtener horarios de un docente
     */
    public function horariosDocente($id, Request $request)
    {
        $docente = Docente::find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        $query = $docente->horarios()
            ->with(['grupo.materia', 'aula']);

        if ($request->has('gestion_id')) {
            $query->whereHas('grupo', function ($q) use ($request) {
                $q->where('gestion_id', $request->gestion_id);
            });
        }

        $horarios = $query->orderBy('dia_semana')
                         ->orderBy('hora_inicio')
                         ->get();

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    /**
     * Obtener horarios de un grupo
     */
    public function horariosGrupo($id, Request $request)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo no encontrado'
            ], 404);
        }

        $horarios = $grupo->horarios()
            ->with(['docente.user', 'aula'])
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    /**
     * Obtener horarios de un aula
     */
    public function horariosAula($id, Request $request)
    {
        $aula = Aula::find($id);

        if (!$aula) {
            return response()->json([
                'success' => false,
                'message' => 'Aula no encontrada'
            ], 404);
        }

        $query = $aula->horarios()
            ->with(['grupo.materia', 'docente.user']);

        if ($request->has('dia_semana')) {
            $query->where('dia_semana', $request->dia_semana);
        }

        if ($request->has('gestion_id')) {
            $query->whereHas('grupo', function ($q) use ($request) {
                $q->where('gestion_id', $request->gestion_id);
            });
        }

        $horarios = $query->orderBy('dia_semana')
                         ->orderBy('hora_inicio')
                         ->get();

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    /**
     * Obtener vista semanal completa
     */
    public function semanal(Request $request)
    {
        $query = Horario::with(['grupo.materia', 'docente.user', 'aula']);

        if ($request->has('gestion_id')) {
            $query->whereHas('grupo', function ($q) use ($request) {
                $q->where('gestion_id', $request->gestion_id);
            });
        }

        $horarios = $query->orderBy('dia_semana')
                         ->orderBy('hora_inicio')
                         ->get()
                         ->groupBy('dia_semana');

        // Organizar por días de la semana
        $semana = [];
        for ($i = 1; $i <= 6; $i++) {
            $semana[$i] = $horarios->get($i, collect());
        }

        return response()->json([
            'success' => true,
            'data' => $semana
        ]);
    }

    /**
     * Validar conflictos de horario
     */
    private function validarConflictos($datos, $excluirId = null)
    {
        $conflictos = [];

        // Verificar conflicto de docente
        $queryDocente = Horario::where('docente_id', $datos['docente_id'])
            ->where('dia_semana', $datos['dia_semana'])
            ->where(function ($q) use ($datos) {
                $q->whereBetween('hora_inicio', [$datos['hora_inicio'], $datos['hora_fin']])
                  ->orWhereBetween('hora_fin', [$datos['hora_inicio'], $datos['hora_fin']])
                  ->orWhere(function ($q2) use ($datos) {
                      $q2->where('hora_inicio', '<=', $datos['hora_inicio'])
                         ->where('hora_fin', '>=', $datos['hora_fin']);
                  });
            });

        if ($excluirId) {
            $queryDocente->where('id', '!=', $excluirId);
        }

        if ($queryDocente->exists()) {
            $conflictos[] = [
                'tipo' => 'docente',
                'mensaje' => 'El docente ya tiene un horario asignado en este horario'
            ];
        }

        // Verificar conflicto de aula
        $queryAula = Horario::where('aula_id', $datos['aula_id'])
            ->where('dia_semana', $datos['dia_semana'])
            ->where(function ($q) use ($datos) {
                $q->whereBetween('hora_inicio', [$datos['hora_inicio'], $datos['hora_fin']])
                  ->orWhereBetween('hora_fin', [$datos['hora_inicio'], $datos['hora_fin']])
                  ->orWhere(function ($q2) use ($datos) {
                      $q2->where('hora_inicio', '<=', $datos['hora_inicio'])
                         ->where('hora_fin', '>=', $datos['hora_fin']);
                  });
            });

        if ($excluirId) {
            $queryAula->where('id', '!=', $excluirId);
        }

        if ($queryAula->exists()) {
            $conflictos[] = [
                'tipo' => 'aula',
                'mensaje' => 'El aula ya está ocupada en este horario'
            ];
        }

        return $conflictos;
    }

    /**
     * Generar horarios automáticamente
     */
    public function generarAutomatico(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'gestion_id' => 'required|exists:gestiones_academicas,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gestión académica requerida',
                    'errors' => $validator->errors()
                ], 422);
            }

            $gestionId = $request->gestion_id;

            // Obtener grupos sin horarios asignados de la gestión
            $gruposSinHorario = Grupo::where('gestion_id', $gestionId)
                ->whereDoesntHave('horarios')
                ->with(['materia'])
                ->get();

            if ($gruposSinHorario->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay grupos sin horarios asignados en esta gestión'
                ], 400);
            }

            // Obtener docentes activos
            $docentes = Docente::activos()
                ->with(['user'])
                ->get();

            if ($docentes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay docentes activos disponibles'
                ], 400);
            }

            // Obtener aulas activas
            $aulas = Aula::where('activa', true)
                ->orderBy('capacidad', 'desc')
                ->get();

            if ($aulas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay aulas activas disponibles'
                ], 400);
            }

            // Horarios disponibles (8:00-20:00, bloques de 2 horas)
            $horariosDisponibles = [
                ['inicio' => '08:00', 'fin' => '10:00'],
                ['inicio' => '10:00', 'fin' => '12:00'],
                ['inicio' => '14:00', 'fin' => '16:00'],
                ['inicio' => '16:00', 'fin' => '18:00'],
                ['inicio' => '18:00', 'fin' => '20:00']
            ];

            $diasSemana = [1, 2, 3, 4, 5]; // Lunes a Viernes

            $horariosGenerados = 0;
            $fallidos = [];

            DB::beginTransaction();

            try {
                foreach ($gruposSinHorario as $grupo) {
                    $horarioAsignado = false;

                    // Intentar asignar un horario para este grupo
                    foreach ($diasSemana as $dia) {
                        if ($horarioAsignado) break;

                        foreach ($horariosDisponibles as $bloque) {
                            if ($horarioAsignado) break;

                            // Buscar docente disponible
                            foreach ($docentes as $docente) {
                                if ($horarioAsignado) break;

                                // Verificar si el docente ya tiene horario en este día/hora
                                $tieneConflictos = Horario::where('docente_id', $docente->id)
                                    ->where('dia_semana', $dia)
                                    ->where(function ($q) use ($bloque) {
                                        $q->whereBetween('hora_inicio', [$bloque['inicio'], $bloque['fin']])
                                          ->orWhereBetween('hora_fin', [$bloque['inicio'], $bloque['fin']])
                                          ->orWhere(function ($q2) use ($bloque) {
                                              $q2->where('hora_inicio', '<=', $bloque['inicio'])
                                                 ->where('hora_fin', '>=', $bloque['fin']);
                                          });
                                    })
                                    ->exists();

                                if ($tieneConflictos) continue;

                                // Verificar carga horaria del docente
                                $cargaActual = $docente->calcularCargaHoraria($gestionId);
                                $cargaMaxima = $docente->carga_horaria_maxima ?? 40;
                                $horasBloque = 2; // 2 horas por bloque

                                if (($cargaActual + $horasBloque) > $cargaMaxima) {
                                    continue;
                                }

                                // Buscar aula disponible
                                foreach ($aulas as $aula) {
                                    // Verificar si el aula ya está ocupada
                                    $aulaOcupada = Horario::where('aula_id', $aula->id)
                                        ->where('dia_semana', $dia)
                                        ->where(function ($q) use ($bloque) {
                                            $q->whereBetween('hora_inicio', [$bloque['inicio'], $bloque['fin']])
                                              ->orWhereBetween('hora_fin', [$bloque['inicio'], $bloque['fin']])
                                              ->orWhere(function ($q2) use ($bloque) {
                                                  $q2->where('hora_inicio', '<=', $bloque['inicio'])
                                                     ->where('hora_fin', '>=', $bloque['fin']);
                                              });
                                        })
                                        ->exists();

                                    if ($aulaOcupada) continue;

                                    // Verificar disponibilidad del docente según su configuración
                                    if (!$docente->estaDisponible($dia, $bloque['inicio'], $bloque['fin'])) {
                                        continue;
                                    }

                                    // Crear el horario
                                    try {
                                        $horario = Horario::create([
                                            'grupo_id' => $grupo->id,
                                            'docente_id' => $docente->id,
                                            'aula_id' => $aula->id,
                                            'dia_semana' => $dia,
                                            'hora_inicio' => $bloque['inicio'],
                                            'hora_fin' => $bloque['fin']
                                        ]);

                                        // Crear notificación para el docente
                                        if ($docente->user_id) {
                                            Notificacion::crear(
                                                'horario_cambio',
                                                'Nuevo horario asignado',
                                                "Se te ha asignado un nuevo horario: {$grupo->materia->nombre} - Grupo {$grupo->numero_grupo} el " . $this->obtenerNombreDia($dia) . " de {$bloque['inicio']} a {$bloque['fin']} en {$aula->nombre}",
                                                $docente->user_id,
                                                "/horarios/{$horario->id}"
                                            );
                                        }

                                        $horariosGenerados++;
                                        $horarioAsignado = true;
                                        break;
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                            }
                        }
                    }

                    if (!$horarioAsignado) {
                        $fallidos[] = "Grupo {$grupo->materia->nombre} - {$grupo->numero_grupo}";
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Se generaron {$horariosGenerados} horarios exitosamente",
                    'data' => [
                        'horarios_generados' => $horariosGenerados,
                        'fallidos' => $fallidos,
                        'total_grupos' => $gruposSinHorario->count()
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar horarios: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar horarios automáticamente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener nombre del día de la semana
     */
    private function obtenerNombreDia($diaSemana)
    {
        $dias = [0 => '', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
        return $dias[$diaSemana] ?? 'Día';
    }
}

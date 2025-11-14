<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Docente;
use App\Models\Horario;
use App\Models\Aula;
use App\Models\Grupo;
use App\Models\GestionAcademica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteController extends Controller
{
    /**
     * Reporte de horarios semanal
     */
    public function horariosSemanal(Request $request)
    {
        try {
            $gestionId = $request->get('gestion_id');
            $query = Horario::with(['grupo.materia', 'docente.user', 'aula']);

            if ($gestionId) {
                $query->whereHas('grupo', function ($q) use ($gestionId) {
                    $q->where('gestion_id', $gestionId);
                });
            }

            $horarios = $query->orderBy('dia_semana')
                ->orderBy('hora_inicio')
                ->get()
                ->groupBy('dia_semana');

            $semana = [];
            $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

            for ($i = 1; $i <= 6; $i++) {
                $semana[$dias[$i - 1]] = $horarios->get($i, collect());
            }

            return response()->json([
                'success' => true,
                'data' => $semana
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de asistencia por docente
     */
    public function asistenciaDocente(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'docente_id' => 'required|exists:docentes,id',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Log de parámetros
            Log::info('Generando reporte de asistencia', [
                'docente_id' => $request->docente_id,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin
            ]);

            // Cargar todas las relaciones necesarias, incluyendo el nuevo docente del grupo
            $asistencias = Asistencia::with([
                'horario.grupo.materia',
                'horario.grupo.docente.user',  // Docente titular del grupo
                'horario.aula',
                'horario.docente.user',
                'docente.user'
            ])
                ->where('docente_id', $request->docente_id)
                ->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
                ->orderBy('fecha', 'desc')
                ->orderBy('hora_registro', 'desc')
                ->get();

            Log::info('Asistencias encontradas', [
                'total' => $asistencias->count(),
                'primera_asistencia' => $asistencias->first() ? [
                    'id' => $asistencias->first()->id,
                    'horario_id' => $asistencias->first()->horario_id,
                    'tiene_horario' => $asistencias->first()->horario ? 'SI' : 'NO',
                    'tiene_grupo' => $asistencias->first()->horario && $asistencias->first()->horario->grupo ? 'SI' : 'NO',
                    'tiene_materia' => $asistencias->first()->horario && $asistencias->first()->horario->grupo && $asistencias->first()->horario->grupo->materia ? 'SI' : 'NO',
                    'tiene_aula' => $asistencias->first()->horario && $asistencias->first()->horario->aula ? 'SI' : 'NO'
                ] : null
            ]);

            // Transformar asistencias para incluir datos formateados
            $asistenciasTransformadas = $asistencias->map(function ($asistencia) {
                $horario = $asistencia->horario;
                $grupo = $horario ? $horario->grupo : null;
                $materia = $grupo ? $grupo->materia : null;
                $aula = $horario ? $horario->aula : null;

                // Log de debug por cada asistencia para ver qué falta
                if (!$horario || !$grupo || !$materia || !$aula) {
                    Log::warning('Asistencia con datos incompletos', [
                        'asistencia_id' => $asistencia->id,
                        'horario_id' => $asistencia->horario_id,
                        'tiene_horario' => $horario ? 'SI' : 'NO',
                        'tiene_grupo' => $grupo ? 'SI' : 'NO',
                        'tiene_materia' => $materia ? 'SI' : 'NO',
                        'tiene_aula' => $aula ? 'SI' : 'NO'
                    ]);
                }

                $materiaTexto = 'N/A';
                if ($materia) {
                    $materiaTexto = $materia->nombre;
                } elseif ($grupo) {
                    $materiaTexto = 'Grupo sin materia';
                } elseif ($horario) {
                    $materiaTexto = 'Horario sin grupo';
                } else {
                    $materiaTexto = 'Sin horario asignado';
                }

                $grupoTexto = 'N/A';
                if ($grupo) {
                    $grupoTexto = ($materia ? $materia->sigla : 'MAT') . ' - Grupo ' . $grupo->numero_grupo;
                } elseif ($horario) {
                    $grupoTexto = 'Sin grupo asignado';
                }

                $aulaTexto = 'N/A';
                if ($aula) {
                    $aulaTexto = $aula->nombre . ' (' . $aula->codigo_aula . ')';
                } elseif ($horario) {
                    $aulaTexto = 'Sin aula asignada';
                }

                return [
                    'id' => $asistencia->id,
                    'fecha' => $asistencia->fecha ? $asistencia->fecha->format('Y-m-d') : null,
                    'fecha_formateada' => $asistencia->fecha ? $asistencia->fecha->format('d/m/Y') : 'N/A',
                    'hora_registro' => $asistencia->hora_registro ?
                        (is_string($asistencia->hora_registro) ? $asistencia->hora_registro : $asistencia->hora_registro->format('H:i:s')) : null,
                    'hora_registro_formateada' => $asistencia->hora_registro ?
                        (is_string($asistencia->hora_registro) ? substr($asistencia->hora_registro, 0, 5) : $asistencia->hora_registro->format('H:i')) : 'N/A',
                    'estado' => $asistencia->estado ?? 'N/A',
                    'materia' => $materiaTexto,
                    'grupo' => $grupoTexto,
                    'aula' => $aulaTexto,
                    'horario_id' => $horario ? $horario->id : null,
                    'grupo_id' => $grupo ? $grupo->id : null,
                    'materia_id' => $materia ? $materia->id : null,
                    'aula_id' => $aula ? $aula->id : null,
                    // Info adicional para debug
                    'debug_info' => [
                        'tiene_horario' => $horario ? true : false,
                        'tiene_grupo' => $grupo ? true : false,
                        'tiene_materia' => $materia ? true : false,
                        'tiene_aula' => $aula ? true : false
                    ]
                ];
            });

            $estadisticas = [
                'total' => $asistencias->count(),
                'presente' => $asistencias->where('estado', 'presente')->count(),
                'ausente' => $asistencias->where('estado', 'ausente')->count(),
                'tardanza' => $asistencias->where('estado', 'tardanza')->count(),
                'justificado' => $asistencias->where('estado', 'justificado')->count(),
                'porcentaje_asistencia' => 0
            ];

            if ($estadisticas['total'] > 0) {
                $estadisticas['porcentaje_asistencia'] = round(
                    (($estadisticas['presente'] + $estadisticas['tardanza']) / $estadisticas['total']) * 100,
                    2
                );
            }

            $docente = Docente::with('user')->find($request->docente_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'docente' => $docente,
                    'docente_nombre' => $docente && $docente->user ? $docente->user->name : 'N/A',
                    'periodo' => [
                        'fecha_inicio' => $request->fecha_inicio,
                        'fecha_fin' => $request->fecha_fin,
                        'fecha_inicio_formateada' => Carbon::parse($request->fecha_inicio)->format('d/m/Y'),
                        'fecha_fin_formateada' => Carbon::parse($request->fecha_fin)->format('d/m/Y'),
                    ],
                    'estadisticas' => $estadisticas,
                    'asistencias' => $asistencias,
                    'asistencias_transformadas' => $asistenciasTransformadas
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en asistenciaDocente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de carga horaria
     */
    public function cargaHoraria(Request $request)
    {
        try {
            $gestionId = $request->get('gestion_id');
            $docentes = Docente::with(['user', 'horarios.grupo.materia', 'horarios.aula'])->get();

            $cargas = [];
            foreach ($docentes as $docente) {
                if (!$docente->user) {
                    continue; // Saltar docentes sin usuario asociado
                }

                $carga = $docente->calcularCargaHoraria($gestionId);
                $maxima = $docente->carga_horaria_maxima ?? 40;

                $cargas[] = [
                    'docente' => $docente->user->name ?? 'Sin nombre',
                    'codigo' => $docente->codigo_docente ?? 'N/A',
                    'carga_actual' => round($carga, 2),
                    'carga_maxima' => $maxima,
                    'porcentaje_uso' => $maxima > 0 ? round(($carga / $maxima) * 100, 2) : 0,
                    'sobrecarga' => $carga > $maxima,
                    'horarios' => $docente->horarios()->when($gestionId, function ($q) use ($gestionId) {
                        $q->whereHas('grupo', function ($q2) use ($gestionId) {
                            $q2->where('gestion_id', $gestionId);
                        });
                    })->count()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $cargas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de ocupación de aulas
     */
    public function aulasOcupacion(Request $request)
    {
        try {
            $gestionId = $request->get('gestion_id');
            $aulas = Aula::with(['horarios.grupo.materia', 'horarios.docente.user'])->get();

            $ocupacion = [];
            foreach ($aulas as $aula) {
                $horariosQuery = $aula->horarios();

                if ($gestionId) {
                    $horariosQuery->whereHas('grupo', function ($q) use ($gestionId) {
                        $q->where('gestion_id', $gestionId);
                    });
                }

                $horarios = $horariosQuery->get();
                $totalHoras = 0;

                foreach ($horarios as $horario) {
                    $inicio = Carbon::parse($horario->hora_inicio);
                    $fin = Carbon::parse($horario->hora_fin);
                    $totalHoras += $inicio->diffInMinutes($fin) / 60;
                }

                $ocupacion[] = [
                    'aula' => $aula->nombre,
                    'codigo' => $aula->codigo_aula,
                    'capacidad' => $aula->capacidad,
                    'total_horarios' => $horarios->count(),
                    'total_horas_semana' => $totalHoras,
                    'porcentaje_ocupacion' => $aula->capacidad > 0 ? round(($horarios->count() / ($aula->capacidad * 6)) * 100, 2) : 0,
                    'horarios' => $horarios
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $ocupacion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar reporte a PDF
     */
    public function exportPDF(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo' => 'required|in:horarios,asistencias,carga_horaria,aulas'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de reporte no válido',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tipo = $request->get('tipo'); // 'horarios', 'asistencias', 'carga_horaria', 'aulas'
            $data = [];

            switch ($tipo) {
                case 'horarios':
                    $gestionId = $request->get('gestion_id');
                    if (!$gestionId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El ID de gestión académica es requerido para el reporte de horarios'
                        ], 422);
                    }
                    $horarios = Horario::with(['grupo.materia', 'docente.user', 'aula'])
                        ->whereHas('grupo', function ($q) use ($gestionId) {
                            $q->where('gestion_id', $gestionId);
                        })
                        ->orderBy('dia_semana')
                        ->orderBy('hora_inicio')
                        ->get();
                    $data['horarios'] = $horarios;
                    break;

                case 'asistencias':
                    $response = $this->asistenciaDocente($request);
                    if ($response->getStatusCode() !== 200) {
                        return $response;
                    }
                    $data = $response->getData(true)['data'];
                    break;

                case 'carga_horaria':
                    $response = $this->cargaHoraria($request);
                    if ($response->getStatusCode() !== 200) {
                        return $response;
                    }
                    $data = $response->getData(true)['data'];
                    break;

                case 'aulas':
                    $response = $this->aulasOcupacion($request);
                    if ($response->getStatusCode() !== 200) {
                        return $response;
                    }
                    $data = $response->getData(true)['data'];
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de reporte no válido'
                    ], 400);
            }

            // Generar HTML para PDF
            $html = $this->generarHTMLReporte($tipo, $data);

            // Generar PDF usando DomPDF
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return response()->streamDownload(function () use ($dompdf) {
                echo $dompdf->output();
            }, 'reporte_' . $tipo . '_' . date('Y-m-d') . '.pdf', [
                'Content-Type' => 'application/pdf'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar reporte a Excel
     */
    public function exportExcel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo' => 'required|in:horarios,asistencias,carga_horaria,aulas'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de reporte no válido',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tipo = $request->get('tipo');
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            switch ($tipo) {
                case 'horarios':
                    $gestionId = $request->get('gestion_id');
                    if (!$gestionId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El ID de gestión académica es requerido para el reporte de horarios'
                        ], 422);
                    }
                    $horarios = Horario::with(['grupo.materia', 'docente.user', 'aula'])
                        ->whereHas('grupo', function ($q) use ($gestionId) {
                            $q->where('gestion_id', $gestionId);
                        })
                        ->orderBy('dia_semana')
                        ->orderBy('hora_inicio')
                        ->get();

                    $sheet->setCellValue('A1', 'Día');
                    $sheet->setCellValue('B1', 'Hora Inicio');
                    $sheet->setCellValue('C1', 'Hora Fin');
                    $sheet->setCellValue('D1', 'Materia');
                    $sheet->setCellValue('E1', 'Grupo');
                    $sheet->setCellValue('F1', 'Docente');
                    $sheet->setCellValue('G1', 'Aula');

                    $row = 2;
                    if ($horarios->isEmpty()) {
                        $sheet->setCellValue('A' . $row, 'No hay horarios registrados para esta gestión académica');
                        $sheet->mergeCells('A' . $row . ':G' . $row);
                    } else {
                        $dias = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        foreach ($horarios as $horario) {
                            $sheet->setCellValue('A' . $row, $dias[$horario->dia_semana] ?? 'N/A');
                            $sheet->setCellValue('B' . $row, $horario->hora_inicio ?? 'N/A');
                            $sheet->setCellValue('C' . $row, $horario->hora_fin ?? 'N/A');
                            $sheet->setCellValue('D' . $row, $horario->grupo->materia->nombre ?? 'N/A');
                            $sheet->setCellValue('E' . $row, $horario->grupo->numero_grupo ?? 'N/A');
                            $sheet->setCellValue('F' . $row, $horario->docente->user->name ?? 'N/A');
                            $sheet->setCellValue('G' . $row, $horario->aula->nombre ?? 'N/A');
                            $row++;
                        }
                    }
                    break;

                case 'asistencias':
                    $response = $this->asistenciaDocente($request);
                    if ($response->getStatusCode() !== 200) {
                        return $response;
                    }
                    $data = $response->getData(true)['data'];
                    $sheet->setCellValue('A1', 'Fecha');
                    $sheet->setCellValue('B1', 'Materia');
                    $sheet->setCellValue('C1', 'Grupo');
                    $sheet->setCellValue('D1', 'Aula');
                    $sheet->setCellValue('E1', 'Estado');
                    $sheet->setCellValue('F1', 'Hora Registro');

                    $row = 2;
                    // Usar datos transformados si están disponibles
                    $asistencias = $data['asistencias_transformadas'] ?? $data['asistencias'] ?? [];

                    if (empty($asistencias)) {
                        $sheet->setCellValue('A' . $row, 'No hay asistencias registradas para este período');
                        $sheet->mergeCells('A' . $row . ':F' . $row);
                    } else {
                        foreach ($asistencias as $asistencia) {
                            // Manejar tanto objetos Eloquent como arrays transformados
                            if (is_array($asistencia)) {
                                // Array transformado - preferir este formato
                                $fecha = $asistencia['fecha_formateada'] ?? 'N/A';
                                $materia = $asistencia['materia'] ?? 'N/A';
                                $grupo = $asistencia['grupo'] ?? 'N/A';
                                $aula = $asistencia['aula'] ?? 'N/A';
                                $estado = $asistencia['estado'] ?? 'N/A';
                                $horaRegistro = $asistencia['hora_registro_formateada'] ?? 'N/A';
                            } else {
                                // Objeto Eloquent
                                $fecha = $asistencia->fecha ?
                                    (is_string($asistencia->fecha) ? $asistencia->fecha : $asistencia->fecha->format('d/m/Y')) : 'N/A';

                                $horaRegistro = 'N/A';
                                if ($asistencia->hora_registro) {
                                    if (is_string($asistencia->hora_registro)) {
                                        $horaRegistro = substr($asistencia->hora_registro, 0, 5);
                                    } else {
                                        $horaRegistro = $asistencia->hora_registro->format('H:i');
                                    }
                                }

                                // Acceso seguro con mensajes descriptivos
                                $materia = 'N/A';
                                if ($asistencia->horario && $asistencia->horario->grupo && $asistencia->horario->grupo->materia) {
                                    $materia = $asistencia->horario->grupo->materia->nombre;
                                } elseif ($asistencia->horario && $asistencia->horario->grupo) {
                                    $materia = 'Grupo sin materia';
                                } elseif ($asistencia->horario) {
                                    $materia = 'Horario sin grupo';
                                } else {
                                    $materia = 'Sin horario';
                                }

                                $grupo = 'N/A';
                                if ($asistencia->horario && $asistencia->horario->grupo) {
                                    $sigla = $asistencia->horario->grupo->materia ? $asistencia->horario->grupo->materia->sigla : 'MAT';
                                    $grupo = $sigla . ' - Grupo ' . $asistencia->horario->grupo->numero_grupo;
                                } elseif ($asistencia->horario) {
                                    $grupo = 'Sin grupo';
                                }

                                $aula = 'N/A';
                                if ($asistencia->horario && $asistencia->horario->aula) {
                                    $aula = $asistencia->horario->aula->nombre . ' (' . $asistencia->horario->aula->codigo_aula . ')';
                                } elseif ($asistencia->horario) {
                                    $aula = 'Sin aula';
                                }

                                $estado = $asistencia->estado ?? 'N/A';
                            }

                            $sheet->setCellValue('A' . $row, $fecha);
                            $sheet->setCellValue('B' . $row, $materia);
                            $sheet->setCellValue('C' . $row, $grupo);
                            $sheet->setCellValue('D' . $row, $aula);
                            $sheet->setCellValue('E' . $row, $estado);
                            $sheet->setCellValue('F' . $row, $horaRegistro);
                            $row++;
                        }
                    }
                    break;

                case 'carga_horaria':
                    $response = $this->cargaHoraria($request);
                    if ($response->getStatusCode() !== 200) {
                        return $response;
                    }
                    $data = $response->getData(true)['data'];
                    $sheet->setCellValue('A1', 'Docente');
                    $sheet->setCellValue('B1', 'Código');
                    $sheet->setCellValue('C1', 'Carga Actual');
                    $sheet->setCellValue('D1', 'Carga Máxima');
                    $sheet->setCellValue('E1', 'Porcentaje Uso');
                    $sheet->setCellValue('F1', 'Sobrecarga');
                    $sheet->setCellValue('G1', 'Total Horarios');

                    $row = 2;
                    if (empty($data)) {
                        $sheet->setCellValue('A' . $row, 'No hay datos de carga horaria para mostrar');
                        $sheet->mergeCells('A' . $row . ':G' . $row);
                    } else {
                        foreach ($data as $carga) {
                            $sheet->setCellValue('A' . $row, $carga['docente'] ?? 'N/A');
                            $sheet->setCellValue('B' . $row, $carga['codigo'] ?? 'N/A');
                            $sheet->setCellValue('C' . $row, $carga['carga_actual'] ?? 0);
                            $sheet->setCellValue('D' . $row, $carga['carga_maxima'] ?? 0);
                            $sheet->setCellValue('E' . $row, ($carga['porcentaje_uso'] ?? 0) . '%');
                            $sheet->setCellValue('F' . $row, ($carga['sobrecarga'] ?? false) ? 'Sí' : 'No');
                            $sheet->setCellValue('G' . $row, $carga['horarios'] ?? 0);
                            $row++;
                        }
                    }
                    break;

                case 'aulas':
                    $response = $this->aulasOcupacion($request);
                    if ($response->getStatusCode() !== 200) {
                        return $response;
                    }
                    $data = $response->getData(true)['data'];
                    $sheet->setCellValue('A1', 'Aula');
                    $sheet->setCellValue('B1', 'Código');
                    $sheet->setCellValue('C1', 'Capacidad');
                    $sheet->setCellValue('D1', 'Total Horarios');
                    $sheet->setCellValue('E1', 'Total Horas/Semana');
                    $sheet->setCellValue('F1', 'Porcentaje Ocupación');

                    $row = 2;
                    if (empty($data)) {
                        $sheet->setCellValue('A' . $row, 'No hay datos de ocupación de aulas para mostrar');
                        $sheet->mergeCells('A' . $row . ':F' . $row);
                    } else {
                        foreach ($data as $aula) {
                            $sheet->setCellValue('A' . $row, $aula['aula'] ?? 'N/A');
                            $sheet->setCellValue('B' . $row, $aula['codigo'] ?? 'N/A');
                            $sheet->setCellValue('C' . $row, $aula['capacidad'] ?? 0);
                            $sheet->setCellValue('D' . $row, $aula['total_horarios'] ?? 0);
                            $sheet->setCellValue('E' . $row, round($aula['total_horas_semana'] ?? 0, 2));
                            $sheet->setCellValue('F' . $row, ($aula['porcentaje_ocupacion'] ?? 0) . '%');
                            $row++;
                        }
                    }
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de reporte no válido'
                    ], 400);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'reporte_' . $tipo . '_' . date('Y-m-d') . '.xlsx';

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar HTML para reporte PDF
     */
    private function generarHTMLReporte($tipo, $data)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte ' . ucfirst($tipo) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4f46e5; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 20px; }
        .fecha { text-align: right; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de ' . ucfirst(str_replace('_', ' ', $tipo)) . '</h1>
        <p class="fecha">Generado el: ' . date('d/m/Y H:i') . '</p>
    </div>';

        switch ($tipo) {
            case 'horarios':
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Materia</th>
                            <th>Grupo</th>
                            <th>Docente</th>
                            <th>Aula</th>
                        </tr>
                    </thead>
                    <tbody>';
                $dias = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                if (empty($data['horarios'])) {
                    $html .= '<tr><td colspan="7" style="text-align: center;">No hay horarios registrados para esta gestión académica</td></tr>';
                } else {
                    foreach ($data['horarios'] as $horario) {
                        $html .= '<tr>
                            <td>' . ($dias[$horario->dia_semana] ?? 'N/A') . '</td>
                            <td>' . ($horario->hora_inicio ?? 'N/A') . '</td>
                            <td>' . ($horario->hora_fin ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($horario->grupo->materia->nombre ?? 'N/A') . '</td>
                            <td>' . ($horario->grupo->numero_grupo ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($horario->docente->user->name ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($horario->aula->nombre ?? 'N/A') . '</td>
                        </tr>';
                    }
                }
                $html .= '</tbody></table>';
                break;

            case 'asistencias':
                $docenteNombre = $data['docente_nombre'] ??
                    (isset($data['docente']) && $data['docente']->user ? $data['docente']->user->name : 'N/A');
                $fechaInicio = $data['periodo']['fecha_inicio_formateada'] ?? $data['periodo']['fecha_inicio'] ?? 'N/A';
                $fechaFin = $data['periodo']['fecha_fin_formateada'] ?? $data['periodo']['fecha_fin'] ?? 'N/A';

                $html .= '<div style="margin-bottom: 20px;">
                    <h2>Docente: ' . htmlspecialchars($docenteNombre) . '</h2>
                    <p>Período: ' . htmlspecialchars($fechaInicio) . ' - ' . htmlspecialchars($fechaFin) . '</p>
                    <p>Total: ' . $data['estadisticas']['total'] . ' | Presente: ' . $data['estadisticas']['presente'] . ' | Ausente: ' . $data['estadisticas']['ausente'] . '</p>
                    <p>Porcentaje de asistencia: ' . $data['estadisticas']['porcentaje_asistencia'] . '%</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Materia</th>
                            <th>Grupo</th>
                            <th>Aula</th>
                            <th>Estado</th>
                            <th>Hora Registro</th>
                        </tr>
                    </thead>
                    <tbody>';

                // Usar datos transformados si están disponibles, sino usar asistencias originales
                $asistencias = $data['asistencias_transformadas'] ?? $data['asistencias'] ?? [];

                if (empty($asistencias)) {
                    $html .= '<tr><td colspan="6" style="text-align: center;">No hay asistencias registradas para este período</td></tr>';
                } else {
                    foreach ($asistencias as $asistencia) {
                        // Manejar tanto objetos Eloquent como arrays transformados
                        if (is_array($asistencia)) {
                            // Array transformado - preferir este formato
                            $fecha = htmlspecialchars($asistencia['fecha_formateada'] ?? 'N/A');
                            $materia = htmlspecialchars($asistencia['materia'] ?? 'N/A');
                            $grupo = htmlspecialchars($asistencia['grupo'] ?? 'N/A');
                            $aula = htmlspecialchars($asistencia['aula'] ?? 'N/A');
                            $estado = ucfirst($asistencia['estado'] ?? 'N/A');
                            $horaRegistro = htmlspecialchars($asistencia['hora_registro_formateada'] ?? 'N/A');
                        } else {
                            // Objeto Eloquent
                            $fecha = $asistencia->fecha ?
                                (is_string($asistencia->fecha) ? $asistencia->fecha : $asistencia->fecha->format('d/m/Y')) : 'N/A';

                            $horaRegistro = 'N/A';
                            if ($asistencia->hora_registro) {
                                if (is_string($asistencia->hora_registro)) {
                                    $horaRegistro = substr($asistencia->hora_registro, 0, 5);
                                } else {
                                    $horaRegistro = $asistencia->hora_registro->format('H:i');
                                }
                            }

                            // Acceso seguro a relaciones anidadas con mensajes descriptivos
                            $materia = 'N/A';
                            if ($asistencia->horario && $asistencia->horario->grupo && $asistencia->horario->grupo->materia) {
                                $materia = htmlspecialchars($asistencia->horario->grupo->materia->nombre);
                            } elseif ($asistencia->horario && $asistencia->horario->grupo) {
                                $materia = 'Grupo sin materia';
                            } elseif ($asistencia->horario) {
                                $materia = 'Horario sin grupo';
                            } else {
                                $materia = 'Sin horario';
                            }

                            $grupo = 'N/A';
                            if ($asistencia->horario && $asistencia->horario->grupo) {
                                $sigla = $asistencia->horario->grupo->materia ? $asistencia->horario->grupo->materia->sigla : 'MAT';
                                $grupo = htmlspecialchars($sigla . ' - Grupo ' . $asistencia->horario->grupo->numero_grupo);
                            } elseif ($asistencia->horario) {
                                $grupo = 'Sin grupo';
                            }

                            $aula = 'N/A';
                            if ($asistencia->horario && $asistencia->horario->aula) {
                                $aula = htmlspecialchars($asistencia->horario->aula->nombre . ' (' . $asistencia->horario->aula->codigo_aula . ')');
                            } elseif ($asistencia->horario) {
                                $aula = 'Sin aula';
                            }

                            $estado = ucfirst($asistencia->estado ?? 'N/A');
                        }

                        $html .= '<tr>
                            <td>' . $fecha . '</td>
                            <td>' . $materia . '</td>
                            <td>' . $grupo . '</td>
                            <td>' . $aula . '</td>
                            <td>' . $estado . '</td>
                            <td>' . $horaRegistro . '</td>
                        </tr>';
                    }
                }
                $html .= '</tbody></table>';
                break;

            case 'carga_horaria':
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>Docente</th>
                            <th>Código</th>
                            <th>Carga Actual</th>
                            <th>Carga Máxima</th>
                            <th>Porcentaje Uso</th>
                            <th>Sobrecarga</th>
                            <th>Total Horarios</th>
                        </tr>
                    </thead>
                    <tbody>';
                if (empty($data)) {
                    $html .= '<tr><td colspan="7" style="text-align: center;">No hay datos de carga horaria para mostrar</td></tr>';
                } else {
                    foreach ($data as $carga) {
                        $html .= '<tr>
                            <td>' . htmlspecialchars($carga['docente'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($carga['codigo'] ?? 'N/A') . '</td>
                            <td>' . $carga['carga_actual'] . ' horas</td>
                            <td>' . $carga['carga_maxima'] . ' horas</td>
                            <td>' . $carga['porcentaje_uso'] . '%</td>
                            <td>' . ($carga['sobrecarga'] ? 'Sí' : 'No') . '</td>
                            <td>' . $carga['horarios'] . '</td>
                        </tr>';
                    }
                }
                $html .= '</tbody></table>';
                break;

            case 'aulas':
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>Aula</th>
                            <th>Código</th>
                            <th>Capacidad</th>
                            <th>Total Horarios</th>
                            <th>Total Horas/Semana</th>
                            <th>Porcentaje Ocupación</th>
                        </tr>
                    </thead>
                    <tbody>';
                if (empty($data)) {
                    $html .= '<tr><td colspan="6" style="text-align: center;">No hay datos de ocupación de aulas para mostrar</td></tr>';
                } else {
                    foreach ($data as $aula) {
                        $html .= '<tr>
                            <td>' . htmlspecialchars($aula['aula'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($aula['codigo'] ?? 'N/A') . '</td>
                            <td>' . $aula['capacidad'] . '</td>
                            <td>' . $aula['total_horarios'] . '</td>
                            <td>' . round($aula['total_horas_semana'], 2) . ' horas</td>
                            <td>' . $aula['porcentaje_ocupacion'] . '%</td>
                        </tr>';
                    }
                }
                $html .= '</tbody></table>';
                break;
        }

        $html .= '</body></html>';
        return $html;
    }
}

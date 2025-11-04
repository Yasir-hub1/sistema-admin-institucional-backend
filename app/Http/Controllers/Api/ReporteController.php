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

            $asistencias = Asistencia::with(['horario.grupo.materia', 'horario.aula'])
                ->where('docente_id', $request->docente_id)
                ->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
                ->orderBy('fecha', 'desc')
                ->get();

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
                    'periodo' => [
                        'fecha_inicio' => $request->fecha_inicio,
                        'fecha_fin' => $request->fecha_fin
                    ],
                    'estadisticas' => $estadisticas,
                    'asistencias' => $asistencias
                ]
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
                    if (empty($data['asistencias'])) {
                        $sheet->setCellValue('A' . $row, 'No hay asistencias registradas para este período');
                        $sheet->mergeCells('A' . $row . ':F' . $row);
                    } else {
                        foreach ($data['asistencias'] as $asistencia) {
                            $sheet->setCellValue('A' . $row, $asistencia->fecha ?? 'N/A');
                            $sheet->setCellValue('B' . $row, $asistencia->horario->grupo->materia->nombre ?? 'N/A');
                            $sheet->setCellValue('C' . $row, $asistencia->horario->grupo->numero_grupo ?? 'N/A');
                            $sheet->setCellValue('D' . $row, $asistencia->horario->aula->nombre ?? 'N/A');
                            $sheet->setCellValue('E' . $row, $asistencia->estado ?? 'N/A');
                            $sheet->setCellValue('F' . $row, $asistencia->hora_registro ?? 'N/A');
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
                $html .= '<div style="margin-bottom: 20px;">
                    <h2>Docente: ' . ($data['docente']->user->name ?? 'N/A') . '</h2>
                    <p>Período: ' . $data['periodo']['fecha_inicio'] . ' - ' . $data['periodo']['fecha_fin'] . '</p>
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
                if (empty($data['asistencias'])) {
                    $html .= '<tr><td colspan="6" style="text-align: center;">No hay asistencias registradas para este período</td></tr>';
                } else {
                    foreach ($data['asistencias'] as $asistencia) {
                        $html .= '<tr>
                            <td>' . ($asistencia->fecha ?? 'N/A') . '</td>
                            <td>' . ($asistencia->horario->grupo->materia->nombre ?? 'N/A') . '</td>
                            <td>' . ($asistencia->horario->grupo->numero_grupo ?? 'N/A') . '</td>
                            <td>' . ($asistencia->horario->aula->nombre ?? 'N/A') . '</td>
                            <td>' . ucfirst($asistencia->estado ?? 'N/A') . '</td>
                            <td>' . ($asistencia->hora_registro ?? 'N/A') . '</td>
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\Docente;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;

class AsistenciaController extends Controller
{
    /**
     * Listar asistencias con filtros
     */
    public function index(Request $request)
    {
        try {
            $query = Asistencia::with(['horario.grupo.materia', 'horario.aula', 'docente.user', 'registradoPor']);

            // Filtro por fecha
            if ($request->has('fecha')) {
                $query->porFecha($request->fecha);
            }

            // Filtro por rango de fechas
            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $query->porRangoFechas($request->fecha_inicio, $request->fecha_fin);
            }

            // Filtro por estado
            if ($request->has('estado')) {
                $query->porEstado($request->estado);
            }

            // Filtro por docente
            if ($request->has('docente_id')) {
                $query->deDocente($request->docente_id);
            }

            // Filtro por horario
            if ($request->has('horario_id')) {
                $query->where('horario_id', $request->horario_id);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'fecha');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $asistencias = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $asistencias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las asistencias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar asistencia manualmente
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'horario_id' => 'required|exists:horarios,id',
                'docente_id' => 'required|exists:docentes,id',
                'fecha' => 'required|date',
                'estado' => 'required|in:presente,ausente,tardanza,justificado',
                'observaciones' => 'nullable|string|max:500',
                'latitud' => 'nullable|numeric|between:-90,90',
                'longitud' => 'nullable|numeric|between:-180,180'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que no exista ya una asistencia para este horario y fecha
            $existente = Asistencia::where('horario_id', $request->horario_id)
                ->where('docente_id', $request->docente_id)
                ->whereDate('fecha', $request->fecha)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un registro de asistencia para este horario y fecha'
                ], 422);
            }

            // Si hay geolocalización, validar distancia al aula
            $geolocalizacionValida = false;
            $distanciaAula = null;
            $direccionGeolocalizacion = null;

            if ($request->has('latitud') && $request->has('longitud')) {
                $horario = Horario::with('aula')->find($request->horario_id);
                $aula = $horario->aula;

                // Coordenadas del aula (deberían estar en la tabla aulas)
                // Por ahora asumimos que el aula tiene coordenadas o las obtenemos de algún lugar
                // Distancia máxima permitida: 100 metros
                $distanciaMaxima = 100;

                // Calcular distancia (fórmula Haversine simplificada)
                if ($aula->latitud && $aula->longitud) {
                    $distanciaAula = $this->calcularDistancia(
                        $request->latitud,
                        $request->longitud,
                        $aula->latitud,
                        $aula->longitud
                    );
                    $geolocalizacionValida = $distanciaAula <= $distanciaMaxima;
                } else {
                    // Si el aula no tiene coordenadas, marcamos como válida si hay coordenadas
                    $geolocalizacionValida = true;
                }

                $direccionGeolocalizacion = $request->get('direccion_geolocalizacion');
            }

            $asistencia = Asistencia::create([
                'horario_id' => $request->horario_id,
                'docente_id' => $request->docente_id,
                'fecha' => $request->fecha,
                'hora_registro' => now(),
                'estado' => $request->estado,
                'metodo_registro' => ($request->has('latitud') && $request->has('longitud')) ? 'geolocalizacion' : 'manual',
                'observaciones' => $request->observaciones,
                'registrado_por' => Auth::id(),
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'direccion_geolocalizacion' => $direccionGeolocalizacion,
                'distancia_aula' => $distanciaAula,
                'geolocalizacion_valida' => $geolocalizacionValida
            ]);

            $asistencia->load(['horario.grupo.materia', 'horario.aula', 'docente.user']);

            // Crear notificación para el docente
            $docente = $asistencia->docente;
            if ($docente && $docente->user_id) {
                $estadoTexto = ucfirst($request->estado);
                $materiaNombre = $asistencia->horario->grupo->materia->nombre ?? 'N/A';
                $grupoNumero = $asistencia->horario->grupo->numero_grupo ?? 'N/A';

                Notificacion::crear(
                    'asistencia',
                    'Asistencia registrada',
                    "Tu asistencia ha sido registrada como {$estadoTexto} para {$materiaNombre} - Grupo {$grupoNumero}",
                    $docente->user_id,
                    "/asistencias/{$asistencia->id}"
                );

                // Si hay ausencias repetidas (3 o más en los últimos 7 días), crear alerta
                $ausenciasRecientes = Asistencia::where('docente_id', $docente->id)
                    ->where('estado', 'ausente')
                    ->whereBetween('fecha', [now()->subDays(7), now()])
                    ->count();

                if ($ausenciasRecientes >= 3 && $request->estado === 'ausente') {
                    Notificacion::crear(
                        'alerta',
                        'Alerta: Múltiples ausencias',
                        "Has registrado {$ausenciasRecientes} ausencias en los últimos 7 días. Por favor, revisa tu situación.",
                        $docente->user_id,
                        "/asistencias?docente_id={$docente->id}"
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Asistencia registrada exitosamente',
                'data' => $asistencia
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la asistencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar asistencia mediante código QR
     */
    public function registrarConQR(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo_qr' => 'required|string',
                'observaciones' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Decodificar el código QR (formato: horario_id|docente_id|fecha)
            $datos = explode('|', $request->codigo_qr);

            if (count($datos) != 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código QR inválido'
                ], 422);
            }

            [$horarioId, $docenteId, $fecha] = $datos;

            // Validar que el horario y docente existan
            $horario = Horario::find($horarioId);
            $docente = Docente::find($docenteId);

            if (!$horario || !$docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Horario o docente no encontrado'
                ], 404);
            }

            // Verificar que no exista ya un registro
            $existente = Asistencia::where('horario_id', $horarioId)
                ->where('docente_id', $docenteId)
                ->whereDate('fecha', $fecha)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un registro de asistencia para este horario y fecha'
                ], 422);
            }

            // Determinar el estado basándose en la hora
            $horaRegistro = now();
            $horaInicio = Carbon::parse($fecha . ' ' . $horario->hora_inicio);
            $minutosRetraso = $horaRegistro->diffInMinutes($horaInicio, false);

            $estado = 'presente';
            if ($minutosRetraso < -15) { // Más de 15 minutos de retraso
                $estado = 'tardanza';
            }

            $asistencia = Asistencia::create([
                'horario_id' => $horarioId,
                'docente_id' => $docenteId,
                'fecha' => $fecha,
                'hora_registro' => $horaRegistro,
                'estado' => $estado,
                'metodo_registro' => 'qr',
                'observaciones' => $request->observaciones,
                'registrado_por' => Auth::id()
            ]);

            $asistencia->load(['horario.grupo.materia', 'horario.aula', 'docente.user']);

            // Crear notificación para el docente
            if ($docente && $docente->user_id) {
                $estadoTexto = ucfirst($estado);
                $materiaNombre = $asistencia->horario->grupo->materia->nombre ?? 'N/A';
                $grupoNumero = $asistencia->horario->grupo->numero_grupo ?? 'N/A';

                Notificacion::crear(
                    'asistencia',
                    'Asistencia registrada con QR',
                    "Tu asistencia ha sido registrada como {$estadoTexto} para {$materiaNombre} - Grupo {$grupoNumero} mediante código QR",
                    $docente->user_id,
                    "/asistencias/{$asistencia->id}"
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Asistencia registrada exitosamente mediante QR',
                'data' => $asistencia
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la asistencia con QR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar código QR para un horario
     */
    public function generarQR($horarioId)
    {
        try {
            $horario = Horario::with(['grupo.materia', 'docente.user', 'aula'])->findOrFail($horarioId);

            // Obtener el docente del horario
            $docenteId = $horario->docente_id;
            $fecha = now()->format('Y-m-d');

            // Crear código QR con formato: horario_id|docente_id|fecha
            $codigoQR = "{$horarioId}|{$docenteId}|{$fecha}";

            // Generar QR en formato base64
            $qrCode = base64_encode(QrCode::format('png')
                ->size(300)
                ->errorCorrection('H')
                ->generate($codigoQR));

            return response()->json([
                'success' => true,
                'data' => [
                    'horario' => $horario,
                    'codigo_qr' => $codigoQR,
                    'qr_image' => 'data:image/png;base64,' . $qrCode,
                    'fecha' => $fecha
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el código QR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener asistencias de un docente
     */
    public function porDocente($docenteId, Request $request)
    {
        try {
            $query = Asistencia::with(['horario.grupo.materia', 'horario.aula'])
                ->deDocente($docenteId);

            // Filtro por rango de fechas
            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $query->porRangoFechas($request->fecha_inicio, $request->fecha_fin);
            }

            // Filtro por estado
            if ($request->has('estado')) {
                $query->porEstado($request->estado);
            }

            $asistencias = $query->orderBy('fecha', 'desc')->get();

            // Calcular estadísticas
            $estadisticas = [
                'total' => $asistencias->count(),
                'presente' => $asistencias->where('estado', 'presente')->count(),
                'ausente' => $asistencias->where('estado', 'ausente')->count(),
                'tardanza' => $asistencias->where('estado', 'tardanza')->count(),
                'justificado' => $asistencias->where('estado', 'justificado')->count(),
            ];

            if ($estadisticas['total'] > 0) {
                $estadisticas['porcentaje_asistencia'] = round(
                    (($estadisticas['presente'] + $estadisticas['tardanza']) / $estadisticas['total']) * 100,
                    2
                );
            } else {
                $estadisticas['porcentaje_asistencia'] = 0;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'asistencias' => $asistencias,
                    'estadisticas' => $estadisticas
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las asistencias del docente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener asistencias de un horario
     */
    public function porHorario($horarioId, Request $request)
    {
        try {
            $horario = Horario::with(['grupo.materia', 'docente.user', 'aula'])->findOrFail($horarioId);

            $query = Asistencia::with(['docente.user'])
                ->where('horario_id', $horarioId);

            // Filtro por rango de fechas
            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $query->porRangoFechas($request->fecha_inicio, $request->fecha_fin);
            }

            $asistencias = $query->orderBy('fecha', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'horario' => $horario,
                    'asistencias' => $asistencias
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las asistencias del horario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una asistencia específica
     */
    public function show($id)
    {
        try {
            $asistencia = Asistencia::with([
                'horario.grupo.materia',
                'horario.grupo.gestion',
                'horario.aula',
                'docente.user',
                'registradoPor'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $asistencia
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Asistencia no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar asistencia
     */
    public function update(Request $request, $id)
    {
        try {
            $asistencia = Asistencia::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'estado' => 'required|in:presente,ausente,tardanza,justificado',
                'observaciones' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $asistencia->update([
                'estado' => $request->estado,
                'observaciones' => $request->observaciones
            ]);

            $asistencia->load(['horario.grupo.materia', 'docente.user']);

            return response()->json([
                'success' => true,
                'message' => 'Asistencia actualizada exitosamente',
                'data' => $asistencia
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la asistencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar asistencia
     */
    public function destroy($id)
    {
        try {
            $asistencia = Asistencia::findOrFail($id);
            $asistencia->delete();

            return response()->json([
                'success' => true,
                'message' => 'Asistencia eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la asistencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar asistencia con geolocalización
     */
    public function registrarConGeolocalizacion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'horario_id' => 'required|exists:horarios,id',
                'docente_id' => 'required|exists:docentes,id',
                'fecha' => 'required|date',
                'latitud' => 'required|numeric|between:-90,90',
                'longitud' => 'required|numeric|between:-180,180',
                'observaciones' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $horario = Horario::with('aula')->findOrFail($request->horario_id);
            $docente = Docente::findOrFail($request->docente_id);

            // Verificar que no exista ya un registro
            $existente = Asistencia::where('horario_id', $request->horario_id)
                ->where('docente_id', $request->docente_id)
                ->whereDate('fecha', $request->fecha)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un registro de asistencia para este horario y fecha'
                ], 422);
            }

            // Validar distancia al aula
            $distanciaMaxima = 100; // metros
            $aula = $horario->aula;
            $geolocalizacionValida = false;
            $distanciaAula = null;

            if ($aula->latitud && $aula->longitud) {
                $distanciaAula = $this->calcularDistancia(
                    $request->latitud,
                    $request->longitud,
                    $aula->latitud,
                    $aula->longitud
                );
                $geolocalizacionValida = $distanciaAula <= $distanciaMaxima;
            } else {
                // Si el aula no tiene coordenadas, permitir pero marcar
                $geolocalizacionValida = true;
            }

            if (!$geolocalizacionValida) {
                return response()->json([
                    'success' => false,
                    'message' => "La geolocalización no es válida. Estás a " . round($distanciaAula, 2) . " metros del aula, máximo permitido: {$distanciaMaxima} metros",
                    'distancia' => round($distanciaAula, 2),
                    'distancia_maxima' => $distanciaMaxima
                ], 422);
            }

            // Determinar estado basándose en la hora
            $horaRegistro = now();
            $horaInicio = Carbon::parse($request->fecha . ' ' . $horario->hora_inicio);
            $minutosRetraso = $horaRegistro->diffInMinutes($horaInicio, false);

            $estado = 'presente';
            if ($minutosRetraso < -15) {
                $estado = 'tardanza';
            }

            $asistencia = Asistencia::create([
                'horario_id' => $request->horario_id,
                'docente_id' => $request->docente_id,
                'fecha' => $request->fecha,
                'hora_registro' => $horaRegistro,
                'estado' => $estado,
                'metodo_registro' => 'geolocalizacion',
                'observaciones' => $request->observaciones,
                'registrado_por' => Auth::id(),
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'direccion_geolocalizacion' => $request->get('direccion_geolocalizacion'),
                'distancia_aula' => $distanciaAula ? round($distanciaAula, 2) : null,
                'geolocalizacion_valida' => true
            ]);

            $asistencia->load(['horario.grupo.materia', 'horario.aula', 'docente.user']);

            // Crear notificación para el docente
            if ($docente && $docente->user_id) {
                $estadoTexto = ucfirst($estado);
                $materiaNombre = $asistencia->horario->grupo->materia->nombre ?? 'N/A';
                $grupoNumero = $asistencia->horario->grupo->numero_grupo ?? 'N/A';

                Notificacion::crear(
                    'asistencia',
                    'Asistencia registrada con geolocalización',
                    "Tu asistencia ha sido registrada como {$estadoTexto} para {$materiaNombre} - Grupo {$grupoNumero} mediante geolocalización",
                    $docente->user_id,
                    "/asistencias/{$asistencia->id}"
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Asistencia registrada exitosamente mediante geolocalización',
                'data' => $asistencia
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la asistencia con geolocalización',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular distancia entre dos puntos geográficos (Haversine)
     */
    private function calcularDistancia($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Radio de la Tierra en metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

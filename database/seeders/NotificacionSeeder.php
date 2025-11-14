<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notificacion;
use App\Models\User;
use Carbon\Carbon;

class NotificacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los usuarios
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No hay usuarios en la base de datos. Ejecuta UserSeeder primero.');
            return;
        }

        $this->command->info('Creando notificaciones de prueba...');

        $tipos = ['horario_cambio', 'asistencia', 'alerta', 'info'];
        $notificacionesCreadas = 0;

        foreach ($users as $user) {
            // Crear 3-5 notificaciones por usuario
            $cantidadNotificaciones = rand(3, 5);

            for ($i = 0; $i < $cantidadNotificaciones; $i++) {
                $tipo = $tipos[array_rand($tipos)];
                $leida = rand(0, 1) === 1; // 50% de probabilidad de estar leída
                $fechaCreacion = Carbon::now()->subDays(rand(0, 30)); // Últimos 30 días

                $notificacion = $this->crearNotificacionPorTipo($tipo, $user->id, $leida, $fechaCreacion);

                if ($notificacion) {
                    $notificacionesCreadas++;
                }
            }
        }

        $this->command->info("✅ {$notificacionesCreadas} notificaciones creadas exitosamente");
    }

    private function crearNotificacionPorTipo($tipo, $userId, $leida, $fechaCreacion)
    {
        $notificacion = null;

        switch ($tipo) {
            case 'horario_cambio':
                $materias = ['Matemáticas', 'Física', 'Química', 'Historia', 'Literatura'];
                $materia = $materias[array_rand($materias)];
                $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                $dia = $dias[array_rand($dias)];
                $aulas = ['A-101', 'B-202', 'C-303', 'Lab-1', 'Auditorio'];
                $aula = $aulas[array_rand($aulas)];

                $notificacion = Notificacion::create([
                    'user_id' => $userId,
                    'tipo' => 'horario_cambio',
                    'titulo' => 'Cambio de horario',
                    'mensaje' => "Tu horario de {$materia} - Grupo " . rand(1, 5) . " ha sido modificado para el {$dia} de " . rand(7, 18) . ":00 a " . rand(8, 19) . ":00 en {$aula}",
                    'accion_url' => '/horarios',
                    'leida' => $leida,
                    'leida_at' => $leida ? $fechaCreacion->copy()->addHours(rand(1, 24)) : null,
                    'created_at' => $fechaCreacion,
                    'updated_at' => $fechaCreacion
                ]);
                break;

            case 'asistencia':
                $estados = ['presente', 'ausente', 'tardanza', 'justificado'];
                $estado = $estados[array_rand($estados)];
                $materias = ['Matemáticas', 'Física', 'Química', 'Historia', 'Literatura'];
                $materia = $materias[array_rand($materias)];

                $titulo = $estado === 'presente' ? 'Asistencia registrada' :
                         ($estado === 'ausente' ? 'Ausencia registrada' :
                         ($estado === 'tardanza' ? 'Tardanza registrada' : 'Asistencia justificada'));

                $notificacion = Notificacion::create([
                    'user_id' => $userId,
                    'tipo' => 'asistencia',
                    'titulo' => $titulo,
                    'mensaje' => "Tu asistencia ha sido registrada como {$estado} para {$materia} - Grupo " . rand(1, 5),
                    'accion_url' => '/asistencias',
                    'leida' => $leida,
                    'leida_at' => $leida ? $fechaCreacion->copy()->addHours(rand(1, 24)) : null,
                    'created_at' => $fechaCreacion,
                    'updated_at' => $fechaCreacion
                ]);
                break;

            case 'alerta':
                $alertas = [
                    [
                        'titulo' => 'Alerta: Múltiples ausencias',
                        'mensaje' => 'Has registrado ' . rand(3, 5) . ' ausencias en los últimos 7 días. Por favor, revisa tu situación.'
                    ],
                    [
                        'titulo' => 'Carga horaria alta',
                        'mensaje' => 'Tu carga horaria actual es de ' . rand(35, 45) . ' horas semanales. Considera revisar tu disponibilidad.'
                    ],
                    [
                        'titulo' => 'Conflicto de horario',
                        'mensaje' => 'Se ha detectado un posible conflicto en tu horario del ' . ['Lunes', 'Martes', 'Miércoles'][array_rand(['Lunes', 'Martes', 'Miércoles'])] . '. Por favor revisa.'
                    ]
                ];
                $alerta = $alertas[array_rand($alertas)];

                $notificacion = Notificacion::create([
                    'user_id' => $userId,
                    'tipo' => 'alerta',
                    'titulo' => $alerta['titulo'],
                    'mensaje' => $alerta['mensaje'],
                    'accion_url' => '/horarios',
                    'leida' => $leida,
                    'leida_at' => $leida ? $fechaCreacion->copy()->addHours(rand(1, 24)) : null,
                    'created_at' => $fechaCreacion,
                    'updated_at' => $fechaCreacion
                ]);
                break;

            case 'info':
                $infos = [
                    [
                        'titulo' => 'Nueva gestión académica',
                        'mensaje' => 'Se ha activado la gestión académica ' . date('Y') . ' - Periodo ' . rand(1, 2)
                    ],
                    [
                        'titulo' => 'Actualización del sistema',
                        'mensaje' => 'El sistema ha sido actualizado con nuevas funcionalidades. Revisa el panel de control.'
                    ],
                    [
                        'titulo' => 'Recordatorio importante',
                        'mensaje' => 'Recuerda actualizar tu disponibilidad horaria para el próximo semestre.'
                    ]
                ];
                $info = $infos[array_rand($infos)];

                $notificacion = Notificacion::create([
                    'user_id' => $userId,
                    'tipo' => 'info',
                    'titulo' => $info['titulo'],
                    'mensaje' => $info['mensaje'],
                    'accion_url' => '/dashboard',
                    'leida' => $leida,
                    'leida_at' => $leida ? $fechaCreacion->copy()->addHours(rand(1, 24)) : null,
                    'created_at' => $fechaCreacion,
                    'updated_at' => $fechaCreacion
                ]);
                break;
        }

        return $notificacion;
    }
}


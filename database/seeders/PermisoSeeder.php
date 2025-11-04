<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;

class PermisoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permisos = [
            // Módulo: Docentes
            ['nombre' => 'ver_docentes', 'modulo' => 'docentes', 'accion' => 'ver', 'descripcion' => 'Ver lista de docentes'],
            ['nombre' => 'crear_docentes', 'modulo' => 'docentes', 'accion' => 'crear', 'descripcion' => 'Crear nuevos docentes'],
            ['nombre' => 'editar_docentes', 'modulo' => 'docentes', 'accion' => 'editar', 'descripcion' => 'Editar docentes'],
            ['nombre' => 'eliminar_docentes', 'modulo' => 'docentes', 'accion' => 'eliminar', 'descripcion' => 'Eliminar docentes'],

            // Módulo: Materias
            ['nombre' => 'ver_materias', 'modulo' => 'materias', 'accion' => 'ver', 'descripcion' => 'Ver lista de materias'],
            ['nombre' => 'crear_materias', 'modulo' => 'materias', 'accion' => 'crear', 'descripcion' => 'Crear nuevas materias'],
            ['nombre' => 'editar_materias', 'modulo' => 'materias', 'accion' => 'editar', 'descripcion' => 'Editar materias'],
            ['nombre' => 'eliminar_materias', 'modulo' => 'materias', 'accion' => 'eliminar', 'descripcion' => 'Eliminar materias'],

            // Módulo: Aulas
            ['nombre' => 'ver_aulas', 'modulo' => 'aulas', 'accion' => 'ver', 'descripcion' => 'Ver lista de aulas'],
            ['nombre' => 'crear_aulas', 'modulo' => 'aulas', 'accion' => 'crear', 'descripcion' => 'Crear nuevas aulas'],
            ['nombre' => 'editar_aulas', 'modulo' => 'aulas', 'accion' => 'editar', 'descripcion' => 'Editar aulas'],
            ['nombre' => 'eliminar_aulas', 'modulo' => 'aulas', 'accion' => 'eliminar', 'descripcion' => 'Eliminar aulas'],

            // Módulo: Grupos
            ['nombre' => 'ver_grupos', 'modulo' => 'grupos', 'accion' => 'ver', 'descripcion' => 'Ver lista de grupos'],
            ['nombre' => 'crear_grupos', 'modulo' => 'grupos', 'accion' => 'crear', 'descripcion' => 'Crear nuevos grupos'],
            ['nombre' => 'editar_grupos', 'modulo' => 'grupos', 'accion' => 'editar', 'descripcion' => 'Editar grupos'],
            ['nombre' => 'eliminar_grupos', 'modulo' => 'grupos', 'accion' => 'eliminar', 'descripcion' => 'Eliminar grupos'],

            // Módulo: Horarios
            ['nombre' => 'ver_horarios', 'modulo' => 'horarios', 'accion' => 'ver', 'descripcion' => 'Ver lista de horarios'],
            ['nombre' => 'crear_horarios', 'modulo' => 'horarios', 'accion' => 'crear', 'descripcion' => 'Crear nuevos horarios'],
            ['nombre' => 'editar_horarios', 'modulo' => 'horarios', 'accion' => 'editar', 'descripcion' => 'Editar horarios'],
            ['nombre' => 'eliminar_horarios', 'modulo' => 'horarios', 'accion' => 'eliminar', 'descripcion' => 'Eliminar horarios'],
            ['nombre' => 'generar_horarios_automatico', 'modulo' => 'horarios', 'accion' => 'generar_automatico', 'descripcion' => 'Generar horarios automáticamente'],
            ['nombre' => 'ver_mi_carga_horaria', 'modulo' => 'horarios', 'accion' => 'ver_propio', 'descripcion' => 'Ver mi carga horaria (docente)'],

            // Módulo: Asistencias
            ['nombre' => 'ver_asistencias', 'modulo' => 'asistencias', 'accion' => 'ver', 'descripcion' => 'Ver lista de asistencias'],
            ['nombre' => 'registrar_asistencia', 'modulo' => 'asistencias', 'accion' => 'registrar', 'descripcion' => 'Registrar asistencia'],
            ['nombre' => 'editar_asistencias', 'modulo' => 'asistencias', 'accion' => 'editar', 'descripcion' => 'Editar asistencias'],
            ['nombre' => 'eliminar_asistencias', 'modulo' => 'asistencias', 'accion' => 'eliminar', 'descripcion' => 'Eliminar asistencias'],
            ['nombre' => 'ver_mis_asistencias', 'modulo' => 'asistencias', 'accion' => 'ver_propio', 'descripcion' => 'Ver mis asistencias (docente)'],

            // Módulo: Gestiones Académicas
            ['nombre' => 'ver_gestiones', 'modulo' => 'gestiones_academicas', 'accion' => 'ver', 'descripcion' => 'Ver gestiones académicas'],
            ['nombre' => 'crear_gestiones', 'modulo' => 'gestiones_academicas', 'accion' => 'crear', 'descripcion' => 'Crear gestiones académicas'],
            ['nombre' => 'editar_gestiones', 'modulo' => 'gestiones_academicas', 'accion' => 'editar', 'descripcion' => 'Editar gestiones académicas'],
            ['nombre' => 'eliminar_gestiones', 'modulo' => 'gestiones_academicas', 'accion' => 'eliminar', 'descripcion' => 'Eliminar gestiones académicas'],

            // Módulo: Usuarios
            ['nombre' => 'ver_usuarios', 'modulo' => 'usuarios', 'accion' => 'ver', 'descripcion' => 'Ver lista de usuarios'],
            ['nombre' => 'crear_usuarios', 'modulo' => 'usuarios', 'accion' => 'crear', 'descripcion' => 'Crear nuevos usuarios'],
            ['nombre' => 'editar_usuarios', 'modulo' => 'usuarios', 'accion' => 'editar', 'descripcion' => 'Editar usuarios'],
            ['nombre' => 'eliminar_usuarios', 'modulo' => 'usuarios', 'accion' => 'eliminar', 'descripcion' => 'Eliminar usuarios'],
            ['nombre' => 'importar_usuarios', 'modulo' => 'usuarios', 'accion' => 'importar', 'descripcion' => 'Importar usuarios desde Excel/CSV'],

            // Módulo: Roles
            ['nombre' => 'ver_roles', 'modulo' => 'roles', 'accion' => 'ver', 'descripcion' => 'Ver lista de roles'],
            ['nombre' => 'crear_roles', 'modulo' => 'roles', 'accion' => 'crear', 'descripcion' => 'Crear nuevos roles'],
            ['nombre' => 'editar_roles', 'modulo' => 'roles', 'accion' => 'editar', 'descripcion' => 'Editar roles'],
            ['nombre' => 'eliminar_roles', 'modulo' => 'roles', 'accion' => 'eliminar', 'descripcion' => 'Eliminar roles'],
            ['nombre' => 'asignar_permisos', 'modulo' => 'roles', 'accion' => 'asignar_permisos', 'descripcion' => 'Asignar permisos a roles'],

            // Módulo: Reportes
            ['nombre' => 'ver_reportes', 'modulo' => 'reportes', 'accion' => 'ver', 'descripcion' => 'Ver reportes'],
            ['nombre' => 'exportar_reportes', 'modulo' => 'reportes', 'accion' => 'exportar', 'descripcion' => 'Exportar reportes (PDF/Excel)'],

            // Módulo: Notificaciones
            ['nombre' => 'ver_notificaciones', 'modulo' => 'notificaciones', 'accion' => 'ver', 'descripcion' => 'Ver notificaciones'],

            // Módulo: Auditoría
            ['nombre' => 'ver_auditoria', 'modulo' => 'auditoria', 'accion' => 'ver', 'descripcion' => 'Ver registro de auditoría'],
        ];

        foreach ($permisos as $permiso) {
            Permiso::firstOrCreate(
                ['nombre' => $permiso['nombre']],
                $permiso
            );
        }
    }
}

# Sistema Académico FICCT - Backend Laravel

## 🚀 Instalación y Configuración

### Requisitos Previos
- PHP 8.2 o superior
- Composer
- PostgreSQL 12 o superior
- Node.js 18 o superior (para el frontend)

### 1. Instalación del Backend

```bash
# Clonar el repositorio
git clone <repository-url>
cd sistema-academico-backend

# Instalar dependencias
composer install

# Configurar variables de entorno
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Configurar base de datos en .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sistema_academico_ficct
DB_USERNAME=postgres
DB_PASSWORD=tu_password

# Ejecutar migraciones y seeders
php artisan migrate:fresh --seed

# Iniciar servidor de desarrollo
php artisan serve
```

### 2. Configuración de Variables de Entorno

Crear archivo `.env` con las siguientes variables:

```env
APP_NAME="Sistema Académico FICCT"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sistema_academico_ficct
DB_USERNAME=postgres
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
SESSION_DOMAIN=localhost

CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
```

### 3. Usuarios de Prueba

Después de ejecutar los seeders, tendrás los siguientes usuarios:

- **Administrador**: admin@ficct.edu.bo / admin123
- **Docentes**:
  - juan.perez@ficct.edu.bo / docente123
  - maria.rodriguez@ficct.edu.bo / docente123
  - carlos.mendoza@ficct.edu.bo / docente123
  - ana.gutierrez@ficct.edu.bo / docente123
  - roberto.silva@ficct.edu.bo / docente123

### 4. Estructura de la Base de Datos

El sistema incluye las siguientes tablas principales:

- **users**: Usuarios del sistema
- **roles**: Roles (admin, coordinador, docente, autoridad)
- **docentes**: Información específica de docentes
- **gestiones_academicas**: Períodos académicos
- **materias**: Materias/cursos
- **aulas**: Aulas y laboratorios
- **grupos**: Grupos de materias por gestión
- **horarios**: Asignación de horarios
- **asistencias**: Registro de asistencias

### 5. API Endpoints Principales

#### Autenticación
- `POST /api/auth/login` - Iniciar sesión
- `POST /api/auth/logout` - Cerrar sesión
- `GET /api/auth/me` - Usuario actual

#### Docentes
- `GET /api/docentes` - Listar docentes
- `POST /api/docentes` - Crear docente
- `GET /api/docentes/{id}` - Ver docente
- `PUT /api/docentes/{id}` - Actualizar docente
- `DELETE /api/docentes/{id}` - Eliminar docente

#### Horarios
- `GET /api/horarios` - Listar horarios
- `POST /api/horarios` - Crear horario
- `POST /api/horarios/validar` - Validar horario
- `GET /api/horarios/semanal` - Vista semanal

#### Asistencias
- `POST /api/asistencias` - Registrar asistencia
- `POST /api/asistencias/qr` - Registrar con QR
- `GET /api/asistencias/docente/{id}` - Asistencias de docente

### 6. Características Implementadas

✅ **Autenticación con Sanctum**
✅ **Sistema de roles y permisos**
✅ **Gestión de docentes, materias, aulas**
✅ **Asignación de horarios con validación de conflictos**
✅ **Registro de asistencias con códigos QR**
✅ **Seeders con datos de prueba**
✅ **API RESTful completa**
✅ **Validaciones robustas**
✅ **Relaciones de base de datos optimizadas**

### 7. Próximos Pasos

- [ ] Implementar servicios de lógica de negocio
- [ ] Crear middleware de validación de roles
- [ ] Implementar generación de reportes PDF/Excel
- [ ] Configurar sistema de notificaciones
- [ ] Crear tests unitarios
- [ ] Implementar funcionalidades específicas (QR Scanner, etc.)

### 8. Comandos Útiles

```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Regenerar autoload
composer dump-autoload

# Ver rutas
php artisan route:list

# Verificar configuración
php artisan config:show

# Ejecutar tests
php artisan test
```

### 9. Estructura del Proyecto

```
backend/
├── app/
│   ├── Http/Controllers/Api/
│   ├── Models/
│   ├── Services/
│   └── ...
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php
└── ...
```

### 10. Soporte

Para soporte técnico o reportar bugs, contactar al equipo de desarrollo.

---

**Desarrollado para la Facultad FICCT** 🎓

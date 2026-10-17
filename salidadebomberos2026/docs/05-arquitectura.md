# Arquitectura y stack tecnológico

## Stack

- PHP 8.1 o superior.
- HTML5, CSS3 y JavaScript sin framework obligatorio.
- PDO con SQLite como base relacional local.
- Apache/XAMPP o servidor embebido de PHP para desarrollo.
- Git/GitHub para control de versiones del equipo.

## Justificación

El stack es adecuado para un MVP web pequeño porque permite construir formularios, sesiones, validaciones y persistencia sin una infraestructura compleja. SQLite reduce la configuración inicial y sigue siendo una base relacional con restricciones y claves foráneas. La solución puede migrar a MySQL/MariaDB si el despliegue futuro lo requiere.

La guía recomienda CodeIgniter 4. Este proyecto mantiene PHP directo con una **separación equivalente a MVC**, decisión que debe ser aprobada y explicada al cliente/docente porque las páginas concentran parte del controlador y la vista.

## Separación de responsabilidades

| Responsabilidad | Archivos principales |
|---|---|
| Modelo y persistencia | `app/db.php`, `app/repository.php`, esquema SQLite. |
| Autenticación y autorización | `app/auth.php`. |
| Validaciones y utilidades | `app/helpers.php`, `app/repository.php`. |
| Control de solicitudes | `index.php`, `login.php`, `admin.php`, `bomberos.php`, `perfil.php`, `api.php`. |
| Vistas y componentes de presentación | HTML de las páginas, `app/layout.php`, `app/forms.php`, `assets/app.css`. |

## Recorrido de una solicitud

1. El navegador solicita una página PHP.
2. La página carga autenticación, repositorio y utilidades.
3. Si es un POST, se verifica el token CSRF y se validan los datos.
4. Se comprueba el rol y, cuando corresponde, la propiedad de la alerta.
5. El repositorio ejecuta una consulta preparada mediante PDO.
6. La acción registra una entrada de bitácora y redirige con un mensaje.
7. La página consulta los datos y compone la vista HTML; la API devuelve JSON.

## Reglas de validación

Las validaciones de alertas se centralizan en `alert_validate()`. Las validaciones de usuario y contraseña se encuentran en `admin.php` y `app/auth.php`. La base agrega restricciones de integridad como segunda barrera.

## Seguridad y despliegue

`app/` y `data/` están bloqueados por `.htaccess`; `dev-server.php` también impide acceder a esas rutas durante el servidor embebido. Las credenciales deben cambiarse inmediatamente en el primer acceso y no deben utilizarse como credenciales productivas.

## Mejora futura de arquitectura

Si el alcance del semestre lo permite, se pueden separar controladores y vistas en directorios propios. No es necesario para el MVP si el equipo puede explicar la separación actual y mantener las responsabilidades documentadas.

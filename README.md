# SalidadeBomberos 2026

Aplicación web de alertas para un cuartel de bomberos voluntarios: el personal autorizado publica alertas y la comunidad las ve en la portada. Construida con **PHP 8.2 y CodeIgniter 4**, con el núcleo original conservado en `app/Legacy/`.

> Proyecto integrador de la Tecnicatura Superior en Ciencia de Datos e Inteligencia Artificial (ISFDyT N°57, Chascomús).

## Cómo está armado

| Parte | Dónde | Qué hace |
|---|---|---|
| Sitio y API | Este repositorio | Portada pública, paneles de administrador y bombero, API en JSON |
| Base de datos | MySQL (Aiven) en producción; SQLite solo para desarrollo local | Usuarios, alertas, contactos, bitácora |
| Notificaciones | API externa `api-alertas` (proyecto aparte en Vercel) | Envía los avisos por Firebase al APK de Android |
| APK de Android | Proyecto aparte (Android Studio) | Solo recibe notificaciones |

**Notificaciones:** al publicar una alerta pública, `Bomberos::enviarPush()` llama a la API `api-alertas` con la clave `ALERTAS_API_KEY`. Las alertas privadas **no** se avisan. El envío directo a Firebase del código anterior queda apagado salvo que se defina `PUSH_DIRECT=1`.

## Requisitos
- PHP 8.2 con las extensiones `intl`, `mbstring`, `curl`, `openssl`, `pdo_sqlite` (desarrollo) y `pdo_mysql` (MySQL).
- Composer.
- Para producción: una base MySQL/MariaDB.

## Ejecutar en tu computadora
1. Copiá `.env.example` como `.env` y completá los valores (el archivo `.env` nunca se sube a Git).
2. Instalá dependencias: `composer install`.
3. Definí el administrador inicial, una sola vez, con variables de entorno (elegí tu propio usuario y una clave de al menos 8 caracteres):
   - Windows: `set BOOTSTRAP_ADMIN_USER=tu.usuario` y `set BOOTSTRAP_ADMIN_PASS=tu-clave` (o ponelas en `.env`).
   - Linux/Mac: `export BOOTSTRAP_ADMIN_USER=tu.usuario BOOTSTRAP_ADMIN_PASS=tu-clave`.
4. Iniciá el servidor: `php spark serve` y abrí `http://localhost:8080/`.

El administrador solo se crea si la base no tiene ninguno. **No hay usuario ni clave en el código.** Al ingresar, el sistema obliga a cambiar la clave provisoria.

## Rutas principales
`/` portada · `/login.php` ingreso · `/admin.php` administración · `/bomberos.php` panel de bomberos · `/perfil.php` perfil · `/api.php` API de alertas (JSON). Cualquier otra dirección responde 404.

## Variables de entorno
Ver `.env.example`. Las principales:

| Variable | Para qué |
|---|---|
| `CI_ENVIRONMENT` | `production` o `development` |
| `app_baseURL` | Dirección pública del sitio, con `https://` y barra final |
| `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexión a la base (`mysql` o `sqlite`) |
| `SESSION_DRIVER` | `database` para guardar sesiones en la base (obligatorio en Vercel) |
| `SESSION_AUTO_CREATE` | `1` solo la primera vez, para crear la tabla de sesiones; después se borra |
| `ALERTAS_API_KEY` | Clave de la API de notificaciones |
| `BOOTSTRAP_ADMIN_USER`, `BOOTSTRAP_ADMIN_PASS` | Crean el primer administrador en una base vacía |
| `PUSH_DIRECT` | `1` reactiva el envío directo a Firebase (apagado por defecto) |

**Nunca** escribas claves en el código, en capturas ni en documentos del repositorio.

## Despliegue
- **Producción actual:** Render (Docker), rama `api`.
- **Vercel:** funciona con el runtime comunitario `vercel-php`. Configuración en `vercel.json`; la entrada es `api/index.php`. La rama de producción se define en Vercel → Settings → Environments → Production. Los Preview usan una base de **prueba** separada.
- Guía de corte y vuelta atrás: `docs/15-guia-corte-y-rollback.md`.
- Tabla de sesiones: se crea con `docs/sql/sessions.sql` o con `SESSION_AUTO_CREATE=1` en el primer ingreso.

## Seguridad
Consultas preparadas, contraseñas con hash, CSRF, roles, escape de HTML, bloqueo por intentos fallidos con IP real, bitácora y cambio de clave provisoria obligatorio desde el servidor. Las alertas privadas no salen en la API ni generan avisos. En producción el sitio no muestra detalles de errores internos.

## Documentación
La carpeta `docs/` tiene el problema, el alcance, los requisitos, el modelo de datos, la arquitectura, las pruebas y las guías. Ver especialmente `13-diagnostico-etapa-1.md` y `14-matriz-pruebas-preview.md`.

## Limitaciones conocidas
- El filtro por zona en las notificaciones depende de la API externa y no está verificado.
- El módulo de suscriptores SMS aparece en el panel pero no está implementado.
- Las claves de cuentas cuyos hashes estuvieron en el historial público de Git deben cambiarse.
- Pruebas de roles, CSRF y persistencia tras redespliegue: pendientes.

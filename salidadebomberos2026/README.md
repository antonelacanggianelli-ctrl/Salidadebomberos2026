# SalidadeBomberos 2026 — CodeIgniter 4

Aplicación web de alertas y evacuación para un cuartel de bomberos. Esta versión utiliza **CodeIgniter 4 como framework principal**, conserva la identidad visual y mantiene los flujos de la aplicación original.

## Requisitos

- PHP 8.2 o superior.
- Extensiones `intl`, `mbstring`, `json`, `pdo_sqlite` y `sqlite3`.
- Apache/XAMPP o servidor embebido de PHP.
- MySQL no es necesario para esta versión: la persistencia actual utiliza SQLite.

## Cómo ejecutar

Desde la raíz del proyecto:

```bash
php spark serve
```

Abrir:

```text
http://localhost:8080/
```

También se puede utilizar el servidor embebido:

```bash
php -S localhost:8080 -t public public/index.php
```

En XAMPP, el DocumentRoot debe apuntar a la carpeta `public/`, no a la raíz del proyecto. Si se coloca dentro de `htdocs`, usar una configuración de VirtualHost o acceder al directorio público según la configuración de Apache.

## Accesos

- Portada pública: `/`
- Administrador: `/login.php?acceso=admin`
- Bomberos: `/login.php?acceso=bombero`
- API de alertas: `/api.php?recurso=alertas`
- API de contactos: `/api.php?recurso=contactos`

La credencial inicial es `admin / Bomberos2026!`. Debe cambiarse en el primer ingreso.

## Estructura de la migración

```text
app/
├── Controllers/
│   ├── Home.php
│   ├── BaseController.php
│   └── LegacyApp.php       # puente de compatibilidad durante la migración
├── Config/Routes.php        # rutas CI4 y rutas heredadas compatibles
└── Legacy/                  # núcleo funcional original conservado
    ├── app/                 # autenticación, repositorio y reglas
    ├── data/                # SQLite
    └── *.php                # páginas originales

public/
├── index.php
└── assets/                  # CSS, logo e imágenes

docs/                        # entregables del proyecto integrador
```

`LegacyApp` permite que CodeIgniter controle el front controller, el enrutamiento y el ciclo de solicitud mientras se conserva el funcionamiento comprobado. El siguiente paso de la migración puede separar `Legacy/` gradualmente en controladores, modelos, filtros y vistas CI4 sin rediseñar la interfaz.

## Base de datos

La base actual se encuentra en `app/Legacy/data/salidadebomberos.sqlite`. Para conservar usuarios y alertas, hacer una copia de este archivo antes de actualizar el proyecto. No debe exponerse públicamente: la aplicación pública sólo debe apuntar al directorio `public/`.

## Documentación académica

La carpeta `docs/` contiene problema, MVP, historias de usuario, modelo de datos, arquitectura, backlog, pruebas, roles, uso de IA y guía de defensa. Completar los nombres, fechas, responsables y resultados pendientes con los datos reales del equipo.

## Seguridad

Se mantienen los controles de la aplicación original: consultas preparadas, contraseñas con hash, CSRF, sesiones, roles, validaciones, escape HTML, bloqueo de intentos, bitácora y protección de carpetas internas. Durante la migración se renombraron los helpers heredados `csrf_*` y `redirect()` para evitar colisiones con funciones globales de CodeIgniter.

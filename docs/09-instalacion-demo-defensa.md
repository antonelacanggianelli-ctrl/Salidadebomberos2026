# Instalación, demo y defensa

## Instalación local (repositorio migrado a CodeIgniter 4)

1. Instalar PHP 8.1 o superior con `pdo_sqlite`, `pdo_mysql` (si vas a usar
   MySQL), `mbstring`, `intl`, `session` y `json`.
2. Instalar [Composer](https://getcomposer.org/) y, parado en la carpeta
   del proyecto, correr `composer install` (crea la carpeta `vendor/`,
   necesaria para que arranque CodeIgniter 4 — no se sube a GitHub).
3. Copiar el archivo `env` a `.env` (en la raíz del proyecto). Por defecto
   no hace falta tocar nada: la app usa SQLite sola. Para usar
   MySQL/MariaDB, ver `docs/11-base-de-datos-mysql-mariadb.md`.
4. Verificar que PHP pueda escribir en `app/Legacy/data/` (ahí vive la
   base SQLite cuando se usa ese motor) y en `writable/` (logs y cache
   propios de CodeIgniter).
5. Levantar el servidor de desarrollo desde la raíz del proyecto:
   `php spark serve` (por defecto en `http://localhost:8080`).
   Alternativa sin spark: `php -S localhost:8000 -t public public/index.php`.
   Si se usa XAMPP con `htdocs`, el *document root* del sitio debe apuntar
   a la carpeta `public/` del proyecto, no a la raíz.
6. Abrir la portada en el navegador.
7. Ingresar con la credencial inicial indicada en el README sólo para el
   primer acceso.
8. Cambiar inmediatamente la contraseña inicial.
9. Crear una cuenta de bombero desde **Administración → Bomberos**.
10. Probar el flujo completo y registrar los resultados en `07-plan-pruebas.md`.


## Recorrido de demo sugerido

1. Mostrar la portada sin alertas.
2. Ingresar como administrador y cambiar la contraseña inicial.
3. Crear un bombero autorizado.
4. Ingresar como bombero.
5. Crear una alerta con severidad alta.
6. Mostrar la alerta en la portada pública y en la API.
7. Cambiarla a controlada y luego a cerrada.
8. Mostrar el historial público.
9. Intentar editar una alerta ajena para demostrar autorización.
10. Mostrar contactos, bitácora y README.

## Decisiones para explicar

- Por qué se definió primero el problema.
- Por qué el MVP no incluye SMS/WhatsApp real.
- Cómo conviven SQLite (desarrollo/demo) y MySQL/MariaDB (elegido por el
  equipo para la entrega) con el mismo código — ver `docs/11-base-de-datos-mysql-mariadb.md`.
- Cómo se separan modelo, control y vista.
- Dónde se aplican validaciones, reglas de negocio y controles de seguridad.
- Qué quedó como backlog y qué riesgos siguen abiertos.

## Limitaciones conocidas

El envío de SMS/WhatsApp no está implementado; los suscriptores sólo se registran para una futura integración o aviso manual. La base incluida es de demostración y debe respaldarse antes de modificarla.

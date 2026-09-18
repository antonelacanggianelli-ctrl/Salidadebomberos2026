# Instalación, demo y defensa

## Instalación local

1. Instalar PHP 8.1 o superior con `pdo_sqlite`, `mbstring`, `session` y `json`.
2. Copiar la carpeta del proyecto dentro de `htdocs` si se usa XAMPP.
3. Verificar que PHP pueda escribir en `data/`.
4. Abrir la portada desde `http://localhost/bomberos/`.
5. Ingresar con la credencial inicial indicada en el README sólo para el primer acceso.
6. Cambiar inmediatamente la contraseña inicial.
7. Crear una cuenta de bombero desde **Administración → Bomberos**.
8. Probar el flujo completo y registrar los resultados en `07-plan-pruebas.md`.

También puede utilizarse el servidor embebido con `php -S localhost:8000 dev-server.php`, copiando el proyecto a una ruta sin el signo `!`.

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
- Por qué se eligió SQLite y cómo se podría migrar a MySQL/MariaDB.
- Cómo se separan modelo, control y vista.
- Dónde se aplican validaciones, reglas de negocio y controles de seguridad.
- Qué quedó como backlog y qué riesgos siguen abiertos.

## Limitaciones conocidas

El envío de SMS/WhatsApp no está implementado; los suscriptores sólo se registran para una futura integración o aviso manual. La base incluida es de demostración y debe respaldarse antes de modificarla.

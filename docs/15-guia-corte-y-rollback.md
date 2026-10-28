# Guía de corte a producción en Vercel y rollback (BORRADOR)

Estado: **no ejecutar todavía**. Render sigue siendo la producción real (rama `api`, commit `00c1a60`).
Esta guía se completa y se aprueba con el responsable antes de usarse.

## 1. Qué ya está verificado (Preview, base de prueba)
Build desde Git, portada, API en JSON, 404 real, login, sesiones en base de datos, alta de usuarios, edición de rango en MySQL 8.4, alertas públicas y privadas, clave provisoria obligatoria, IP real en la bitácora, un solo camino de notificaciones. Ver `14-matriz-pruebas-preview.md`.

## 2. Datos que NO hay que migrar
La base real ya está en Aiven y Render la usa. Vercel Production solo tiene que apuntar a **esa misma base**. No hay copia de datos.

## 3. Antes del corte (checklist)
- [ ] Fecha de corte acordada con el responsable (no el mismo día de la exposición, o con Render como respaldo).
- [ ] Respaldo de la base real en Aiven (copia de seguridad del servicio) con fecha anotada.
- [ ] Aiven: confirmar plan vigente (los créditos de prueba vencen a fin de noviembre).
- [ ] Verificar en Vercel → Environment Variables (Production) que `DB_NAME` apunta a la base REAL y no a `salidabomberos_test`.
- [ ] Decidir la rama de producción (ver sección 4).
- [ ] Confirmar que el APK solo recibe notificaciones y no depende de la dirección del sitio.

## 4. Rama de producción (decisión pendiente)
- Opción A: en Vercel → Settings → Environments → Production → Branch Tracking, usar `integracion/preparacion-produccion`. Rápido; no toca `main`.
- Opción B: unir la rama a `main` con una Pull Request, tras revisar las diferencias que hoy tiene `main` (7 commits que `api` no tiene).
Hasta decidir, **no subir nada a `main`**: publicaría Production de Vercel.

## 5. Variables de Production (Vercel)
Ya cargadas (solo Production): `CI_ENVIRONMENT`, `ALERTAS_API_KEY`, `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
Agregar: `SESSION_DRIVER=database` y `app_baseURL` con la dirección final del sitio.
Tabla de sesiones: o se ejecuta `docs/sql/sessions.sql` en la base real, o se agrega `SESSION_AUTO_CREATE=1` solo para el primer ingreso y se quita después.
NO agregar: `PUSH_DIRECT`, `BOOTSTRAP_ADMIN_*` (ya existe un administrador).

## 6. Corte
1. Respaldo hecho y anotado.
2. Cargar las variables de la sección 5.
3. Publicar la rama elegida en Production.
4. Verificar: portada, `/login.php`, `/api.php` (JSON), una URL inexistente (404), ingreso de un usuario, Bitácora con IP real, Vercel → Logs sin errores.
5. Prueba de notificación **solo con aviso previo** a quienes tengan el APK: una alerta `PRUEBA - ignorar`, cerrarla enseguida.
6. Monitorear Logs durante las primeras horas.

## 7. Rollback
- **Código:** Vercel → Deployments → versión anterior → Instant Rollback.
- **Base de datos:** el único cambio de esquema es la tabla nueva `app_sessions`, que no afecta a Render. Un rollback de código **no** revierte datos escritos después del corte (alertas, usuarios, bitácora): quedan en la base compartida.
- **Volver a Render:** Render sigue encendido con la misma base. Mientras ambos estén activos, cargar alertas en un solo sitio a la vez.
- **Sesiones:** son independientes (Render usa archivos, Vercel la base); al cambiar de sitio hay que volver a ingresar.

## 8. Después del corte
- Render se apaga solo cuando el responsable lo apruebe.
- Cambiar las claves de las cuentas cuyos hashes estuvieron en el repositorio público (después de la exposición).
- Limpiar el historial de Git solo si se decide (operación delicada).

## 9. Pendientes conocidos
Filtro por zona en las notificaciones (depende de la API externa), módulo de suscriptores SMS sin implementar, pruebas de roles/CSRF/persistencia/respaldo/móvil, README y `.env.example`, Pull Request.

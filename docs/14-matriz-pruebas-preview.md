# Matriz de pruebas – Preview en Vercel (27/10/2026)

**Entorno:** Preview de Vercel · rama `integracion/preparacion-produccion` · commits `8b623f0`, `fb73c5b` y el del paquete 3
**Base de datos:** MySQL 8.4 en Aiven, base de PRUEBA `salidabomberos_test` (separada de la real)
**Notificaciones:** desactivadas a propósito (no se cargó `ALERTAS_API_KEY` en Preview)
**Evidencia:** capturas de pantalla de la prueba (Antonela) y revisión del código

Estados: ✅ aprobado con evidencia · ⏳ pendiente · ⚠️ funciona con observación

| Caso | Entorno | Resultado esperado | Resultado obtenido | Evidencia | Estado |
|---|---|---|---|---|---|
| Build desde Git | Preview | Despliegue "Ready" sin configuración manual oculta | Ready en 19 s | Captura de Deployments | ✅ |
| Portada | Preview | Se ve con estilos e imágenes | Se ve completa ("Sin alertas activas") | Captura | ✅ |
| `/api.php` | Preview | JSON válido | JSON válido con `generado`, `filtros`, `resumen`, `alertas` | Captura | ✅ |
| `/assets/app.css` | Preview | CSS servido como archivo estático | Se sirve el CSS | Captura | ✅ |
| Conexión a MySQL de prueba | Preview | Tablas creadas y consultas OK | Panel y alertas funcionan | Capturas de paneles | ✅ |
| Login administrador | Preview | Ingreso y redirección al panel | Ingresa al panel de administrador | Captura | ✅ |
| Persistencia de sesión (base de datos) | Preview | Sesión se mantiene entre solicitudes | Navegó todas las pestañas del panel sin volver al login | Relato + capturas | ✅ |
| Logout y nuevo ingreso | Preview | Cierra y permite volver a entrar | Cerró sesión e ingresó de nuevo | Relato | ✅ |
| Alta de usuario bombero | Preview | Usuario creado | Se creó `prueba01` (cuartelero) | Captura de Mi perfil | ✅ |
| Login bombero | Preview | Ingreso al panel de bombero | Ingresó a "Mis alertas" | Captura | ✅ |
| Alta de alerta | Preview | Alerta guardada y visible | Alerta #1 publicada y listada | Captura | ✅ |
| Fallo del push no rompe la publicación | Preview | Alerta guardada y aviso de push no enviado | Aviso "La notificacion push NO se pudo enviar" | Captura | ✅ (esperado: sin clave en Preview) |
| Edición de rango de usuario (MySQL 8.4) | Preview | Guarda sin error | "Datos de prueba01 actualizados", rango `sargento` | Captura | ✅ |
| Cambio obligatorio de contraseña provisoria | Preview (paquete 4) | Con clave provisoria solo se puede usar Mi perfil; al cambiarla se libera el panel | Alta con clave provisoria: el usuario queda en Mi perfil con el aviso. Tras cambiar la clave entra a Nueva alerta. Acceso directo a otra URL estando provisoria: verificado en prueba local, sin captura en Vercel | Capturas + prueba local | ⚠️ falta captura del bloqueo directo en Vercel |
| Logs visibles en Vercel | Preview | Errores en Vercel → Logs | Aparece `enviarPush: falta ALERTAS_API_KEY` (STDERR) | Capturas de Logs | ✅ |
| Un solo camino de notificaciones | Preview (`fb73c5b`) | Con la alerta #3 solo se ejecuta `enviarPush`, sin la línea `PUSH:` del envío interno | Detalle del log de las 15:35: Accepted → `enviarPush` → Closing, sin línea `PUSH:` | Captura del detalle del log | ✅ |
| Alertas creadas sin envío doble | Preview | 1 envío por alerta | 1 línea `enviarPush` por alerta | Capturas de Logs | ✅ |
| Administrador inicial desde variables (base vacía) | Local (SQLite) | Sin variables: no se crea; con variables: se crea y permite ingresar | Verificado en prueba local | Prueba local (no en Vercel) | ⚠️ pendiente en Vercel |
| Login sin caja de credenciales | Preview | No muestra usuario/clave iniciales | La captura del Preview (pestaña Bomberos, con el admin ya ingresado) no es concluyente: esa caja solo aparecía antes del primer ingreso del admin. Verificado en prueba local con base vacía | Captura + prueba local | ⚠️ |
| URL inexistente | Preview (paquete 3) | 404 | Página "404" en lugar de la portada | Captura | ✅ |
| `/api.php` como JSON | Preview (paquete 3) | `Content-Type: application/json` | Chrome lo abre con el visor de JSON (opción de formato); lista 3 alertas | Captura | ✅ |
| API pública no expone datos internos | Preview | Sin usuarios de login en la respuesta | `reporta` es el nombre para mostrar (`full_name`), publicado a propósito; el usuario de login no aparece | Revisión de código + captura | ✅ |
| IP real en la bitácora | Preview (paquete 4) | Registra la IP pública del visitante | El ingreso de las 16:26 muestra una IP pública real; los registros anteriores muestran `127.0.0.1` (dirección interna del runtime) | Captura de la Bitácora | ✅ |
| Alerta privada no se difunde por push | Preview (paquete 4) | Aviso "guardada como privada", sin notificación | Alerta #4: "guardada como privada ... no se envió notificación" (captura); en prueba local, 1 sola llamada de 2 alertas | Captura + prueba local | ⚠️ falta confirmar en Logs que no hay `enviarPush` a las 16:30 |
| Alerta privada fuera de API y push | Preview | No se difunde | No verificado | — | ⏳ |
| Restricciones entre roles | Preview | Bombero no accede al panel de admin | No verificado | — | ⏳ |
| CSRF inválido | Preview | Rechazo | No verificado | — | ⏳ |
| Persistencia tras redespliegue | Preview | Datos y usuarios se conservan | No verificado | — | ⏳ |
| Respaldo y restauración | Preview | Restauración probada | No verificado | — | ⏳ |
| Navegación móvil | Preview | Sitio usable en celular | No verificado | — | ⏳ |

## Observaciones
1. El aviso de la alerta dice "ver writable/logs", pero en Vercel los logs van a **Vercel → Logs**. Texto a corregir.
2. El administrador inicial estuvo escrito en `app/Legacy/app/config.php` en un repositorio público (ya sacado del código en el paquete 2, pero sigue en el historial de Git). Hay que cambiar esa clave en toda base que la tenga.
3. La bitácora registra `::1` como origen: no lee la IP real detrás del proxy.
4. El módulo "Suscriptores SMS" aparece en el panel pero no está implementado de verdad.
5. El formulario de alta de alertas dice que la zona se usa "para las notificaciones push por zona", pero el filtro por zona depende de la API externa `api-alertas` y no está verificado: ajustar el texto o verificar.
6. Producción real sigue en Render (rama `api`, commit `00c1a60`); no fue modificada.

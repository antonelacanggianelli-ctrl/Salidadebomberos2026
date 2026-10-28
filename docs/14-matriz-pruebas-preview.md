# Matriz de pruebas – Preview en Vercel (27/10/2026)

**Entorno:** Preview de Vercel · rama `integracion/preparacion-produccion` · commits `8b623f0`, `fb73c5b` y el del paquete 3
**Base de datos:** MySQL 8.4 en Aiven, base de PRUEBA `salidabomberos_test` (separada de la real)
**Notificaciones:** desactivadas a propósito (no se cargó `ALERTAS_API_KEY` en Preview)
**Evidencia:** capturas de pantalla de la prueba (Antonela) y revisión del código

Estados: ✅ aprobado con evidencia · ⏳ pendiente · ⚠️ funciona con observación

| Caso | Entorno | Resultado esperado | Resultado obtenido | Evidencia | Estado |
|---|---|---|---|---|---|
| Build desde Git | Preview | Despliegue "Ready" sin configuración manual oculta | Ready en 11-19 s en todos los commits de la rama, incluidos `.gitignore` y la baja del SQLite | Capturas de Deployments | ✅ |
| Preview sigue funcionando sin la base SQLite del repo | Preview (tras retirar el SQLite) | `/api.php` y el sitio responden igual | `/api.php` devuelve el JSON con las alertas de la base MySQL de prueba | Captura | ✅ |
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
| Limpieza del repositorio (rama de trabajo) | Preview | Sin SQLite, sesiones ni copia anidada; el sitio sigue igual | Reportado por Antonela: base SQLite, sesiones y copia anidada retiradas, todo funciona | Relato (falta captura de Deployments tras la Parte 4) | ⚠️ confirmar con captura |
| Login sin caja de credenciales | Preview y Production | No muestra usuario/clave iniciales | La captura del Preview (pestaña Bomberos, con el admin ya ingresado) no es concluyente: esa caja solo aparecía antes del primer ingreso del admin. Verificado en prueba local con base vacía | Captura + prueba local | ⚠️ |
| URL inexistente | Preview (paquete 3) | 404 | Página "404" en lugar de la portada | Captura | ✅ |
| `/api.php` como JSON | Preview (paquete 3) | `Content-Type: application/json` | Chrome lo abre con el visor de JSON (opción de formato); lista 3 alertas | Captura | ✅ |
| API pública no expone datos internos | Preview | Sin usuarios de login en la respuesta | `reporta` es el nombre para mostrar (`full_name`), publicado a propósito; el usuario de login no aparece | Revisión de código + captura | ✅ |
| IP real en la bitácora | Preview (paquete 4) | Registra la IP pública del visitante | El ingreso de las 16:26 muestra una IP pública real; los registros anteriores muestran `127.0.0.1` (dirección interna del runtime) | Captura de la Bitácora | ✅ |
| Alerta privada no se difunde por push | Preview (paquete 4) | Aviso "guardada como privada", sin notificación | Alerta #4: "guardada como privada ... no se envió notificación" (captura); en prueba local, 1 sola llamada de 2 alertas | Captura + prueba local | ⚠️ falta confirmar en Logs que no hay `enviarPush` a las 16:30 |
| Alerta privada fuera de API y push | Preview (paquete 4) | La alerta privada no aparece en `/api.php` ni genera push | `/api.php` lista 3 alertas activas (#1, #2, #3) y no incluye la privada #4; el aviso de la #4 dice "sin notificación" | Capturas de la API y del aviso | ⚠️ falta confirmar en Logs que no hay `enviarPush` a las 16:30 |
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

## Prueba productiva en Vercel (28/10/2026, base real `defaultdb` compartida con Render)

**Entorno:** Production de Vercel · rama `integracion/preparacion-produccion` · commit `7891bfa` (redeploy tras corregir variables)
**Hallazgo inicial:** el primer despliegue mostró "Whoops!" porque `DB_HOST`, `DB_PORT`, `DB_USER` y `DB_PASS` de Production tenían valores locales (`127.0.0.1`, `root`, puerto repetido). Se corrigieron con los valores de Render.

| Caso | Resultado esperado | Resultado obtenido | Evidencia | Estado |
|---|---|---|---|---|
| Conexión a la base real | `/api.php` lista las mismas alertas que Render | 9 alertas activas, idénticas a `onrender.com/api.php` (última: #11) | Capturas de ambas APIs | ✅ |
| API en JSON | `Content-Type` JSON | Vercel se abre con el visor JSON; Render sale como texto | Capturas | ✅ |
| Login administrador y de bombero (base real) | Ingreso y sesión estable | Ingresos de `admin` y de un bombero registrados en la Bitácora | Captura de la Bitácora | ✅ |
| Cambio de clave provisoria (base real) | Obliga a cambiarla | Un bombero ingresó, cambió la clave y salió (22:19-22:22) | Captura de la Bitácora | ✅ |
| IP real en la Bitácora | IP pública del visitante | Ingresos de Vercel con IP pública; los de Render muestran `::1` | Captura | ✅ |
| Sesiones en base de datos (tabla creada con `SESSION_AUTO_CREATE`) | Sesión se mantiene entre páginas | Navegó entre paneles ya autenticada | Captura | ✅ |
| `SESSION_AUTO_CREATE` retirada de Production | Variable borrada y las sesiones siguen funcionando | Tras el redeploy sin la variable, la portada muestra sesión abierta ("Mi Panel / Salir") y el cierre de sesión vuelve a mostrar "Acceso Bomberos" | Capturas de la portada | ✅ |
| Branch Tracking vuelto a `main` | Sin publicación automática accidental | Reportado por Antonela: vuelto a `main` | Relato | ✅ (reportado) |
| Portada, login, 404 y celular en Production | Cargan con su diseño / 404 real / usable en el celular | Portada con 9 alertas activas y estado de sesión visible ("Mi Panel / Salir" con sesión abierta, "Acceso Bomberos / Administración" sin sesión); `/login.php` con formulario; `/nada` muestra la página 404; portada correcta en el celular | Capturas de escritorio y celular (28/10/2026) | ✅ |

**Nota:** la base es compartida. Un cambio de clave hecho desde Vercel también vale en Render.

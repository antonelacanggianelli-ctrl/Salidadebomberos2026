# Diagnóstico – Etapa 1 (migración a Vercel)

Fecha: 27/10/2026 · Repo: `antonelacanggianelli-ctrl/Salidadebomberos2026` (clonado público, solo lectura)

## 1. Tabla de estado

| Servicio | Repositorio | Rama | Commit | Motor de datos | Estado | Pendientes |
|---|---|---|---|---|---|---|
| GitHub | Salidadebomberos2026 | `main` | `887dd55` (PR #38) | — | Verificado | `main` está 7 commits por delante de `api`; sus diferencias se evalúan aparte |
| Render | Salidadebomberos2026 | `api` | `00c1a60` | MySQL externo (Aiven) | Live, Docker, plan Free | Producción real: no se modifica hasta el corte |
| Vercel | Salidadebomberos2026 (conectado el 27/10) | Producción: `main`; pruebas: `integracion/preparacion-produccion` | Preview verificado (ver matriz) | MySQL de prueba en Aiven | Preview funcionando | Variables de Production ya cargadas; falta el corte |

## 2. Ramas (recalculado contra `origin/main`)

| Rama | Commit | Adelante | Atrás | Acción |
|---|---|---|---|---|
| `main` | 887dd55 | — | — | Base |
| `Visual` | 669d3ed | 1 | 0 | Integrar fix de `rank` en `user_update()` + `docs/12-modulo-login-y-usuarios.md` |
| `Joaquín-Barrera` | fd53746 | 3 | 23 | Recuperar solo `push.js`, `firebase-messaging-sw.js`, cambios de portada; sin archivos de depuración |
| `api` | 00c1a60 | 0 | 7 | Nada pendiente |
| `antonela` | c6188be | 0 | 32 | Nada pendiente |
| `Romina-Loredo` | 938bf7f | 0 | 27 | Nada pendiente |
| `Zoe-Rivero` | 62eba9f | 0 | 47 | Nada pendiente |

Coincide con la revisión previa. Recomendación: **cherry-pick / recuperación selectiva**, no merge de `Joaquín-Barrera` (23 commits atrás: pisaría avances).

## 3. Hallazgos verificados en el código

1. **Sesiones**: `app/Legacy/app/auth.php` usa `session_start()` nativo (líneas 26 y 32). `app/Config/Session.php` no alcanza. En Vercel hay que usar un manejador compartido (base de datos).
2. **Base por defecto SQLite** (`DB_DRIVER` ≠ mysql → `db_connect_sqlite()`); el esquema se crea automáticamente en la primera solicitud (`db_install_sqlite` / versión MySQL). Hay que sacarlo de las solicitudes públicas.
3. **SQLite versionado con datos**: `app/Legacy/data/salidadebomberos.sqlite` (y su copia anidada) está en Git público. Tiene 4 usuarios, 4 alertas, 3 contactos y 33 registros de bitácora. Hay que confirmar si son reales: si lo son, rotar contraseñas de esos usuarios (el historial de Git conserva el archivo).
4. **Sesiones versionadas**: 15 archivos `ci_session*` en `salidadebomberos2026/writable/session/`.
5. **Copia anidada** `salidadebomberos2026/` con 796 archivos versionados (de 1.649 en total).
6. **Rutas** (`app/Config/Routes.php`): `api.php` está solo como GET (hay POST para tokens push) y la ruta final `(:any)` devuelve la portada para cualquier URL (no hay 404 real).
7. **Firebase**: `push.php` valida `is_file(FCM_SERVICE_ACCOUNT_KEY)`, es decir, espera una **ruta a archivo**, no el JSON. En Vercel no hay `/etc/secrets/`. El `Dockerfile` copia credenciales desde `/etc/secrets/` (solo Render).
8. **API externa** `https://api-alertas.vercel.app/api/enviar` llamada desde `Controllers/Bomberos.php` y `Helpers/app/Helpers/alertas_helper.php` (anidado mal). Su código no está en el repo.
9. **Código de prueba**: `Home.php` (prueba Firestore), `api/hola.js` (con CORS `*`), carpeta `nulgit`.
10. **`baseURL`** fijo a `localhost:8080`; `forceGlobalSecureRequests=false`; `proxyIPs` vacío.
11. **Pruebas**: `tests/` solo tiene los ejemplos del framework, ninguna cubre flujos propios.
12. **Composer**: PHP `^8.2`, `kreait/firebase-php 7.0`, y una excepción de advisory (`PKSA-y2cr-5h3j-g3ys`) a revisar.
13. **No existe** `vercel.json` ni carpeta `.vercel`.

## 4. Viabilidad en Vercel (con fuentes consultadas hoy)

- Vercel no soporta PHP oficialmente: se usa el runtime comunitario `vercel-php`, que Vercel lista como comunitario. El README del runtime muestra `vercel-php@0.7.1` y declara soporte PHP 7.4–8.3, por lo que **PHP 8.2 es posible**. Es mantenido por una sola persona: riesgo a documentar.
- Sin disco persistente: SQLite, sesiones en archivo y `writable/` no sirven como almacenamiento.
- **Conclusión**: viable con condiciones: (a) MySQL/MariaDB externo con TLS, (b) sesiones en base de datos, (c) logs a stderr, (d) entrada `api/index.php` + `vercel.json`, (e) fijar versión exacta del runtime tras probar el build en Preview.
- **Alternativa mínima si el Preview falla**: frontend/entrada en Vercel y PHP en otro host. Solo se propone con evidencia del fallo.

## 5. Decisiones y accesos que faltan

1. Render → Settings: rama, commit, `DB_DRIVER` (¿mysql o sqlite?), ¿disco persistente?, ¿dónde está el MySQL? *(no pegues contraseñas; solo nombres de variables y valores no secretos)*.
2. Vercel → Settings → Git y Build: repo conectado, rama, Root Directory.
3. ¿Los datos del SQLite versionado son reales?
4. ¿Se conserva `api-alertas.vercel.app` o se unifica el envío push dentro de la app? (recomendado: un solo flujo)
5. Proveedor de MySQL (el plan gratuito cambia seguido; verificar vigente).
6. Responsable que aprueba el pase productivo.

## 6. Actualización posterior

La rama de trabajo `integracion/preparacion-produccion` se creó desde `api` (la que despliega Render), no desde `main`. Los resultados de las pruebas están en `14-matriz-pruebas-preview.md`.

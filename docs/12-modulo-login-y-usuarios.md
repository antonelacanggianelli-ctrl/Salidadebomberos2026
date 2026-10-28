# 12 — Documentación técnica: módulo de login y usuarios

**Responsable:** Romi (Programadora — login y usuarios)

El módulo cubre tres cosas del plan de trabajo: la autenticación de
administradores y bomberos, la gestión de las cuentas del personal y la
validación de permisos del lado del servidor.

## Arquitectura

El módulo está migrado a **controladores y vistas de CodeIgniter 4**, pero
reutiliza las funciones de seguridad de `app/Legacy/app/` (sesión, CSRF,
bloqueo por intentos, bitácora). Así las reglas de seguridad son una sola y
las comparten las partes migradas y las que todavía corren por el puente
`LegacyApp`.

| Archivo | Qué hace |
|---|---|
| `app/Controllers/Auth.php` | Ingreso (`login`) y salida (`logout`). |
| `app/Controllers/Perfil.php` | Autogestión de cada usuario: datos de contacto y contraseña. |
| `app/Controllers/Admin.php` | Gestión del personal: listado, alta, edición, suspensión y baja. El resto del panel de administración sigue en `app/Legacy/admin.php`. |
| `app/Filters/RoleFilter.php` | Filtro de CodeIgniter que exige sesión con un rol antes de llegar al controlador. |
| `app/Views/auth/login.php` | Pantalla de ingreso con pestañas Administración / Bomberos. |
| `app/Views/perfil/index.php` | Pantalla "Mi perfil". |
| `app/Views/admin/usuarios.php`, `usuario_form.php` | Listado y formulario de usuarios. |
| `app/Legacy/app/auth.php` | Sesión, CSRF, login, logout, `require_role()`, reglas de contraseña. |
| `app/Legacy/app/repository.php` | Consultas sobre la tabla `users` (`user_create`, `user_update`, etc.). |
| `app/Legacy/app/config.php` | Constantes de seguridad (tiempos, intentos, largo de contraseña). |

## Rutas

Definidas en `app/Config/Routes.php`:

| Ruta | Controlador | Acceso |
|---|---|---|
| `login.php` (GET/POST) | `Auth::login` | Público |
| `logout.php` (GET) | `Auth::logout` | Público |
| `admin.php` (GET/POST) | `Admin::index` | Filtro `role:admin` |
| `bomberos.php` (GET/POST) | `Bomberos::index` | Filtro `role:bombero` |
| `perfil.php` (GET/POST) | `Perfil::index` | Filtro `role:admin,bombero` |

Dentro de `admin.php`, este módulo atiende las vistas `?vista=usuarios`,
`usuario_nuevo` y `usuario_editar`, y las acciones POST `usuario_guardar`,
`usuario_estado` y `usuario_eliminar`.

## Modelo de datos

Tabla `users` (se crea sola en el primer arranque, en SQLite o MySQL/MariaDB;
ver `app/Legacy/app/db.php`):

| Columna | Descripción |
|---|---|
| `id` | Clave primaria. |
| `username` | Único. Entre 4 y 30 caracteres: letras, números, `.`, `-`, `_`. Se guarda en minúsculas. |
| `password_hash` | Hash de la contraseña (`password_hash` con `PASSWORD_DEFAULT`). Nunca se guarda la contraseña. |
| `full_name` | Nombre y apellido (mínimo 3 caracteres). |
| `role` | `admin` o `bombero`. Se fija al crear la cuenta. |
| `rank`, `phone` | Cargo y teléfono de contacto. `rank` es palabra reservada en MySQL 8: en SQL se escribe siempre `` `rank` ``. |
| `active` | 1 = habilitada, 0 = suspendida. |
| `must_change_password` | 1 = la contraseña es provisoria y se pide cambiarla al ingresar. |
| `created_at`, `created_by` | Fecha de alta y administrador que la creó. |
| `last_login_at` | Último ingreso. |

Tablas auxiliares: `login_attempts` (intentos de ingreso, para el bloqueo) y
`audit_log` (bitácora).

## Autenticación

**Flujo de ingreso** (`Auth::login` → `attempt_login()`):

1. Se verifica el token CSRF del formulario.
2. Si hay **5 intentos fallidos en los últimos 15 minutos** para ese usuario
   o esa IP, se rechaza y se registra `login_bloqueado` en la bitácora.
3. Se busca el usuario y se valida la contraseña con `password_verify()`.
   Si el usuario no existe o la clave es incorrecta, el mensaje es siempre
   el mismo ("Usuario o contraseña incorrectos") para no revelar qué
   usuarios existen.
4. Se rechazan las cuentas suspendidas.
5. Se rechaza el **acceso cruzado**: una cuenta de administración no entra
   por la pestaña Bomberos ni al revés (caso de prueba T-11).
6. Si el algoritmo de hash cambió, la contraseña se vuelve a hashear
   (`password_needs_rehash`).
7. Se regenera el id de sesión (`session_regenerate_id(true)`) para evitar
   fijación de sesión, se guarda `last_login_at` y se registra `ingreso`.
8. Si la contraseña es provisoria, se redirige a "Mi perfil" para cambiarla.

**Sesión** (`session_boot()`):

- Cookie `sdb_session` con `httponly`, `SameSite=Lax` y `secure` cuando se
  usa HTTPS.
- Cierre automático tras **30 minutos de inactividad**.
- En cada pedido se relee el usuario desde la base: si la cuenta se
  suspendió o eliminó mientras la sesión estaba abierta, la sesión se
  cierra.

**Contraseñas** (`password_problem()`): mínimo 8 caracteres, deben combinar
letras y números y coincidir con la confirmación. Al cambiarla desde el
perfil se exige la contraseña actual y que la nueva sea distinta.

**CSRF:** todos los formularios llevan un token aleatorio de 32 bytes por
sesión, que se compara con `hash_equals()`. Si no coincide, responde 403.

**Primer arranque:** se crea el administrador `admin` con contraseña
provisoria (ver `config.php`). La pantalla de ingreso muestra una ayuda
solo mientras ese administrador nunca haya ingresado.

## Gestión de usuarios (solo administrador)

| Acción | Reglas |
|---|---|
| Alta | Valida usuario (formato y que no esté repetido), nombre y contraseña. Se puede marcar la contraseña como provisoria. |
| Edición | El rol **no** se toma del formulario: se lee de la base, para que no se pueda convertir un admin en bombero (o al revés) manipulando el POST. La contraseña solo cambia si se completa. |
| Suspender / habilitar | Un administrador no puede suspenderse a sí mismo. Siempre debe quedar al menos un administrador activo. |
| Baja | No se puede eliminar la propia cuenta ni el último administrador activo. Si el usuario tiene alertas publicadas, **se suspende en lugar de borrarse** para conservar el historial. |

Cada acción queda en la bitácora: `usuario_creado`, `usuario_editado`,
`usuario_activado`, `usuario_desactivado`, `usuario_eliminado`.

## Permisos del lado del servidor

Los permisos no dependen de ocultar botones en la interfaz. Se validan en
tres capas:

1. **Ruta:** `RoleFilter` corta el pedido antes de llegar al controlador si
   no hay sesión o el rol no corresponde.
2. **Controlador:** cada controlador vuelve a llamar a `require_role()`.
   Si alguien olvida el filtro en una ruta nueva, la página sigue
   protegida.
3. **Alerta:** en el panel de bomberos, editar, cambiar de estado (incluido
   **cerrar**) o eliminar una alerta pasa por `own_alert_or_fail()`, que
   rechaza cualquier alerta creada por otro integrante.

## Bitácora

`audit()` guarda usuario, acción, detalle, IP y fecha. Eventos de este
módulo: `ingreso`, `salida`, `login_bloqueado`, `perfil_actualizado`,
`clave_cambiada` y los de gestión de usuarios. Se consulta desde
Administración → Bitácora.

## Configuración

En `app/Legacy/app/config.php`:

| Constante | Valor | Uso |
|---|---|---|
| `SESSION_IDLE_TIMEOUT` | 1800 s | Cierre por inactividad. |
| `LOGIN_MAX_ATTEMPTS` | 5 | Intentos fallidos antes del bloqueo. |
| `LOGIN_LOCK_WINDOW` | 900 s | Duración de la ventana de bloqueo. |
| `MIN_PASSWORD_LENGTH` | 8 | Largo mínimo de contraseña. |

## Correcciones

- **`rank` en MySQL 8:** `user_update()` usaba `rank` sin comillas
  invertidas y la edición de usuarios y de "Mi perfil" fallaba en MySQL 8,
  donde `RANK` es palabra reservada. Se escribió como `` `rank` ``, igual
  que en el `INSERT`.

## Cómo probarlo

| Caso | Pasos | Resultado esperado |
|---|---|---|
| Login correcto | Ingresar con un bombero en la pestaña Bomberos. | Entra a `bomberos.php`. |
| Credenciales incorrectas (T-03) | Contraseña errónea. | "Usuario o contraseña incorrectos". |
| Bloqueo (T-04) | 5 contraseñas erróneas seguidas. | Bloqueo por 15 minutos y `login_bloqueado` en la bitácora. |
| Acceso cruzado (T-11) | Usuario `admin` en la pestaña Bomberos. | Rechazado con aviso de cambiar de pestaña. |
| Acceso sin sesión | Abrir `admin.php` sin ingresar. | Redirige a `login.php`. |
| Rol incorrecto | Con sesión de bombero, abrir `admin.php`. | Redirige a `bomberos.php` con "No tienes permisos". |
| Alerta ajena | Con un bombero, abrir `bomberos.php?vista=editar&id=<alerta de otro>`. | "Esa alerta no existe o fue creada por otro integrante". |
| Baja con historial | Eliminar un bombero que tiene alertas. | La cuenta queda suspendida, no borrada. |
| Último admin | Suspender al único administrador activo. | Rechazado. |
| Contraseña provisoria | Ingresar con una cuenta recién creada. | Redirige a "Mi perfil" para cambiarla. |

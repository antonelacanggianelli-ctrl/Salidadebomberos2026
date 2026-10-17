# Historias de usuario y requisitos

## Historias de usuario del MVP

| ID | Historia | Criterio de aceptación |
|---|---|---|
| HU-01 | Como visitante, quiero ver las alertas vigentes para saber qué ocurre. | La portada muestra alertas publicadas que no están cerradas. |
| HU-02 | Como visitante, quiero ver severidad, ubicación e instrucciones para actuar correctamente. | Cada tarjeta presenta esos datos escapados y legibles. |
| HU-03 | Como administrador, quiero crear y suspender cuentas para controlar quién publica. | Sólo un administrador autenticado puede administrar usuarios. |
| HU-04 | Como bombero autorizado, quiero iniciar sesión con mi cuenta para publicar alertas. | Una cuenta activa con rol bombero accede sólo a su panel. |
| HU-05 | Como bombero, quiero cargar una alerta con datos obligatorios para informar un incidente. | La alerta no se guarda si faltan título, tipo, severidad, ubicación o descripción válida. |
| HU-06 | Como bombero, quiero actualizar el estado de mi alerta para reflejar la situación real. | Puede pasarla a controlada o cerrada y la portada refleja el cambio. |
| HU-07 | Como administrador, quiero supervisar cualquier alerta para corregir información. | El administrador puede consultar y modificar alertas del conjunto. |
| HU-08 | Como usuario del sistema, quiero que las acciones relevantes queden registradas. | Ingresos, cambios y eliminaciones generan registros en la bitácora. |
| HU-09 | Como administrador, quiero gestionar contactos para mantener actualizada la portada. | Puede crear, editar, ordenar y eliminar contactos. |
| HU-10 | Como equipo, queremos consultar alertas mediante JSON para una cartelera. | `api.php?recurso=alertas` responde JSON de sólo lectura. |

## Requisitos funcionales

- RF-01: gestionar usuarios con roles `admin` y `bombero`.
- RF-02: autenticar usuarios y cerrar sesiones.
- RF-03: publicar, listar, modificar, controlar, cerrar y eliminar alertas.
- RF-04: filtrar alertas por estado, severidad y texto en administración.
- RF-05: mostrar alertas públicas sólo si están publicadas y no cerradas.
- RF-06: administrar contactos de la portada.
- RF-07: registrar auditoría de acciones relevantes.
- RF-08: ofrecer API JSON de alertas y contactos en modo lectura.
- RF-09: permitir actualizar perfil y contraseña.
- RF-10: conservar y consultar el historial de alertas cerradas.

## Requisitos no funcionales

- RNF-01: utilizar una base de datos relacional.
- RNF-02: separar responsabilidades de autenticación, persistencia, utilidades y presentación.
- RNF-03: proteger formularios POST contra CSRF.
- RNF-04: almacenar contraseñas con hash y no en texto plano.
- RNF-05: escapar la salida HTML y utilizar consultas preparadas.
- RNF-06: documentar instalación, configuración, respaldo y uso.
- RNF-07: permitir ejecución con PHP 8.1 o superior y PDO SQLite.

## Reglas de negocio

1. Sólo usuarios activos pueden ingresar.
2. El rol de la cuenta determina el panel disponible.
3. Un bombero sólo puede operar sobre alertas creadas por él.
4. No puede dejarse el sistema sin un administrador activo.
5. Una alerta cerrada no se muestra entre las alertas vigentes.
6. Las alertas se ordenan por severidad y fecha.
7. Una cuenta con alertas asociadas se suspende en vez de eliminarse para preservar el historial.
8. Toda acción POST requiere token CSRF válido.

# Alcance del proyecto

## Solución ideal

Una plataforma integral de gestión de emergencias que incluya alertas públicas, usuarios y permisos, historial, estadísticas, notificaciones automáticas por SMS/WhatsApp, aplicación móvil, mapas, geolocalización, reportes avanzados, integración con servicios externos y múltiples cuarteles.

## MVP obligatorio

El MVP se limita al núcleo que resuelve el problema durante el semestre:

- Portada pública con alertas vigentes, severidad, ubicación, descripción, instrucciones, punto de encuentro, contactos y cerradas recientes.
- Login diferenciado para administradores y bomberos.
- Alta, modificación, suspensión y eliminación de usuarios por administración.
- Alta, consulta, modificación, cambio de estado y eliminación de alertas.
- Restricción para que cada bombero opere sólo sobre sus propias alertas.
- Estados `activa`, `controlada` y `cerrada`.
- Persistencia relacional en SQLite mediante PDO.
- Validaciones de datos y reglas de negocio.
- Protección CSRF, sesiones, hash de contraseñas, roles y bitácora.
- README de instalación y API JSON de sólo lectura para alertas y contactos.

## Funciones deseables / backlog futuro

| Prioridad | Función | Motivo por el que queda fuera del MVP |
|---|---|---|
| Alta | Notificaciones automáticas por SMS/WhatsApp | Requiere proveedor, credenciales, costos y pruebas externas. |
| Media | Mapas y geolocalización | Requiere definir proveedor cartográfico y alcance de datos. |
| Media | Estadísticas históricas | No es necesaria para publicar y consultar alertas. |
| Media | Aplicación móvil | La aplicación web responsive cubre el flujo principal. |
| Baja | Integración con múltiples cuarteles | Amplía el modelo de permisos y despliegue. |
| Baja | Exportación de reportes | Es una mejora de gestión, no parte del núcleo. |

## Fuera de alcance explícito

No se incluye despacho operativo de unidades, seguimiento GPS de vehículos, diagnóstico automático de emergencias, envío real de mensajes a terceros, gestión médica ni reemplazo de los canales oficiales de emergencia.

## Criterio de aceptación del MVP

El MVP se considera aceptado cuando el flujo completo **login → carga de alerta → persistencia → consulta pública → cambio de estado → historial** funciona en una instalación limpia y los casos de seguridad y permisos definidos en `07-plan-pruebas.md` no presentan errores críticos.

## Decisiones de alcance

La gestión de suscriptores por SMS se eliminó de la aplicación: sin un envío real de mensajes, la lista de números no aportaba valor y podía confundir a los vecinos. El envío de mensajes queda expresamente fuera del MVP; los avisos al instante se resuelven con las notificaciones push del navegador.

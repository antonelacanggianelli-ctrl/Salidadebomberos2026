# Plan y matriz de pruebas

## Preparación

Ejecutar la aplicación con PHP 8.1+, PDO SQLite habilitado y una copia limpia de `data/`. Para cada caso registrar fecha, persona que prueba, resultado, observación y captura o URL local.

## Casos de prueba

| ID | Caso | Resultado esperado | Resultado a registrar |
|---|---|---|---|
| T-01 | Abrir portada sin alertas | Se muestra estado sin alertas y contactos iniciales. | Pendiente de ejecución local. |
| T-02 | Login admin válido | Ingresa al panel admin y exige cambio inicial de clave. | Pendiente de ejecución local. |
| T-03 | Login con clave incorrecta | Mensaje genérico y registro del intento. | Pendiente de ejecución local. |
| T-04 | Cinco intentos fallidos | Se activa bloqueo temporal. | Pendiente de ejecución local. |
| T-05 | Crear bombero como admin | La cuenta aparece activa y puede ingresar. | Pendiente de ejecución local. |
| T-06 | Crear alerta con campos vacíos | No se guarda y se informan validaciones. | Pendiente de ejecución local. |
| T-07 | Crear alerta válida | Se guarda en SQLite y aparece en el panel. | Pendiente de ejecución local. |
| T-08 | Consultar portada | La alerta publicada aparece ordenada por severidad. | Pendiente de ejecución local. |
| T-09 | Controlar y cerrar alerta | Deja de ser vigente y pasa al historial. | Pendiente de ejecución local. |
| T-10 | Editar alerta ajena como bombero | Se rechaza la operación y no se modifican datos. | Pendiente de ejecución local. |
| T-11 | Acceder a admin como bombero | Se redirige al panel correspondiente. | Pendiente de ejecución local. |
| T-12 | Enviar POST sin CSRF | Responde con error de token y no cambia datos. | Pendiente de ejecución local. |
| T-13 | Insertar HTML en una alerta | Se muestra como texto, no como código ejecutable. | Pendiente de ejecución local. |
| T-14 | API de alertas | Devuelve JSON sólo de alertas públicas vigentes. | Pendiente de ejecución local. |
| T-15 | API con POST | Responde 405 y no modifica datos. | Pendiente de ejecución local. |
| T-16 | Eliminar usuario con alertas | Se suspende y se conserva el historial. | Pendiente de ejecución local. |
| T-17 | Intentar eliminar último admin | La operación es rechazada. | Pendiente de ejecución local. |
| T-18 | Cambiar contraseña | La nueva clave cumple política y permite nuevo ingreso. | Pendiente de ejecución local. |
| T-19 | Acceder a `/app/` y `/data/` | Apache/router devuelve 403. | Pendiente de ejecución local. |
| T-20 | Backup y restauración | La copia de `data/` conserva los registros. | Pendiente de ejecución local. |

## Criterio de calidad

No debe quedar ningún error crítico abierto en el flujo del MVP. Los casos pendientes deben ejecutarse en el entorno del equipo y completarse antes de la defensa; esta matriz no debe presentarse como evidencia ejecutada hasta completar la columna de resultados.

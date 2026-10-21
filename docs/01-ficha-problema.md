# Ficha de problema

## Nombre provisorio

**SalidadeBomberos! — Central de alertas y evacuación.**

## Problema o necesidad

En una institución o comunidad, ante un incendio, rescate, fuga de gas u otra emergencia, la información suele comunicarse por canales dispersos, mensajes informales o avisos verbales. Esto dificulta saber cuál es la situación vigente, qué instrucciones seguir, dónde ocurrió el incidente y si el estado ya fue controlado o cerrado.

El proyecto propone una aplicación web que centraliza la publicación de alertas por parte del personal autorizado y ofrece a la comunidad una portada pública con información clara, actualizada y ordenada por severidad.

## ¿Quién tiene el problema?

Los actores afectados son:

- **Comunidad y visitantes:** necesitan consultar rápidamente el estado de la emergencia, la ubicación, las indicaciones y los contactos.
- **Personal de bomberos autorizado:** necesita cargar y actualizar alertas sin depender de una persona intermediaria.
- **Administrador del cuartel:** necesita autorizar usuarios, supervisar publicaciones, administrar contactos y conservar trazabilidad.

## ¿Qué sucede actualmente?

En el escenario de referencia, la información se transmite por llamadas, mensajes, grupos o carteleras. Esos medios no ofrecen una vista única del estado, no siempre dejan historial ordenado y pueden producir versiones desactualizadas o contradictorias.

## Dificultades y consecuencias

La situación puede producir pérdida de tiempo, información incompleta, duplicación de avisos, dificultad para identificar quién publicó una alerta y demora en comunicar el cierre o control de un incidente.

## Información involucrada

La aplicación registra usuarios, roles, alertas, tipo de emergencia, severidad, estado, ubicación, descripción, instrucciones, punto de encuentro, fecha, responsable, contactos públicos y bitácora de acciones.

## Resultado útil esperado

Se espera que una persona pueda consultar desde una página pública el estado actual y las instrucciones principales, mientras que el personal autorizado pueda publicar y mantener la información desde un panel interno.

## Criterios observables de éxito

1. Un administrador puede autorizar y suspender cuentas.
2. Un bombero autorizado puede iniciar sesión y publicar una alerta válida.
3. La alerta publicada aparece en la portada pública ordenada por severidad.
4. La alerta puede pasar por los estados `activa`, `controlada` y `cerrada`.
5. Una alerta cerrada deja de aparecer como vigente y pasa al historial.
6. Un bombero no puede editar ni eliminar la alerta de otro bombero.
7. Las operaciones relevantes quedan registradas en la bitácora.
8. La aplicación puede instalarse siguiendo el README y persiste los datos en SQLite.

## Riesgos y dudas iniciales

El envío automático de SMS o WhatsApp requiere un proveedor externo, costos, credenciales y una definición de alcance; por eso no forma parte del MVP obligatorio. También debe acordarse con el docente el entorno definitivo de despliegue y la política de respaldo de la base.

## Validación con el cliente

La aprobación del problema y del MVP debe registrarse mediante una revisión con el docente/cliente, indicando fecha, participantes, observaciones y decisiones tomadas.

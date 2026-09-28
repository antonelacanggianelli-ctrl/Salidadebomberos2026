# 11 — Base de datos: soporte de MySQL/MariaDB (Semana 2)

## Contexto

El plan de trabajo original pedía MySQL/MariaDB para la persistencia. El
repositorio migrado a CodeIgniter 4 trajo la base en **SQLite** (así lo
indicaba el propio README), sin que quedara registrada una decisión de
equipo al respecto.

## Que se hizo

En vez de elegir "uno u otro", la aplicación ahora **soporta los dos
motores en serio**, sin código duplicado para el resto del sistema:

- Por defecto sigue usando **SQLite** (`app/Legacy/data/salidadebomberos.sqlite`).
  No requiere instalar nada aparte: así puede seguir trabajando cualquiera
  del equipo, y es lo que conviene usar para la demo de defensa (cero
  dependencias externas).
- Si se configuran las variables de entorno de abajo, la aplicación usa
  **MySQL/MariaDB** en su lugar, con el mismo esquema (tablas, columnas,
  índices) traducido a sintaxis MySQL.
- El motor se elige en **un solo lugar** (`DB_DRIVER`), y tanto el código
  Legacy (`app/Legacy/app/db.php`) como el Model de CodeIgniter
  (`app/Models/AlertModel.php`, vía `app/Config/Database.php`) respetan
  la misma variable, así que nunca leen de bases distintas.

Esto se probó de punta a punta contra una instancia real de MariaDB (no
solo se escribió "a ciegas"): creación de la base vacía, arranque de la
app, creación automática de las tablas, login de administrador y lectura
de alertas, todo funcionando igual que con SQLite.

## Cómo activar MySQL/MariaDB

1. Crear la base y el usuario (ajustar la clave):

   ```sql
   CREATE DATABASE salidadebomberos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'salidadebomberos'@'localhost' IDENTIFIED BY 'una-clave-segura';
   GRANT ALL PRIVILEGES ON salidadebomberos.* TO 'salidadebomberos'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Copiar `env` a `.env` (si todavía no existe) y completar:

   ```
   DB_DRIVER = mysql
   DB_HOST   = 127.0.0.1
   DB_PORT   = 3306
   DB_NAME   = salidadebomberos
   DB_USER   = salidadebomberos
   DB_PASS   = una-clave-segura
   ```

3. Levantar la app normalmente (`php spark serve` o el servidor embebido).
   La primera visita crea las tablas y el usuario administrador inicial
   automáticamente, igual que con SQLite.

Si no se completa nada, la app sigue funcionando con SQLite sin que haga
falta tocar un solo archivo — así nadie del equipo se rompe por esto
mientras se decide si el proyecto final se entrega con MySQL o con SQLite.

## Decisión del equipo

El equipo definió usar **MySQL/MariaDB** para la entrega final. SQLite
queda disponible igual como modo por defecto para desarrollo rápido sin
instalar nada, pero cada integrante debe configurar su `.env` local con
`DB_DRIVER = mysql` para trabajar en las mismas condiciones que se van a
entregar.

## Qué falta / a decidir en equipo

- Definir si el MySQL de la entrega final corre en un hosting/VPS
  compartido o si cada integrante usa su MySQL local (XAMPP/Laragon/WAMP)
  y se entrega un dump (`mysqldump`) al final. Mientras tanto, cada uno
  usa su MySQL local con las credenciales de su propio `.env`.
- Si se opta por un servidor compartido para dejar de depender del XAMPP
  de cada uno, definir dónde se aloja y cargar esas credenciales en el
  `.env` de cada máquina (nunca commitear `.env` con credenciales reales).

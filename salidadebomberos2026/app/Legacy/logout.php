<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

send_security_headers();
logout();

// Sesion nueva y limpia, solo para transportar el mensaje de despedida.
session_boot();
session_regenerate_id(true);
flash('ok', 'Cerraste sesion correctamente.');
sdb_redirect('login.php');

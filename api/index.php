<?php
/**
 * Entrada de la aplicación para Vercel (runtime vercel-php).
 * Vercel envía TODAS las solicitudes a este archivo y desde acá se arranca
 * CodeIgniter 4 (public/index.php), igual que lo haría Apache en Render.
 */

// Vercel termina el HTTPS en su proxy: lo informamos a la aplicación
// para que las cookies de sesión salgan con el atributo Secure.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

$raiz = dirname(__DIR__);

// CodeIgniter calcula la ruta pedida a partir de SCRIPT_NAME. Si quedara
// como /api/index.php, una URL como /api.php se interpretaría mal.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $raiz . '/public/index.php';

chdir($raiz . '/public');
require $raiz . '/public/index.php';

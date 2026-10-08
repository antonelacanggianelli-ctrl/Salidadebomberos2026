<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Ingreso y salida del personal (Semana 4 del plan: login migrado a
 * CodeIgniter 4).
 *
 * Igual que el panel de bomberos, reutiliza las funciones de sesion, CSRF,
 * bloqueo por intentos fallidos y bitacora de app/Legacy/app/auth.php, asi
 * las reglas de seguridad no cambian. Lo que cambia es la organizacion:
 * la logica queda en este Controller y el HTML en Views/auth/login.php.
 */
class Auth extends Controller
{
    public function login()
    {
        require_once APPPATH . 'Legacy/app/auth.php';

        send_security_headers();
        session_boot();

        try {
            // Ya autenticado: directo al panel que le corresponde.
            $logged = current_user();
            if ($logged !== null) {
                sdb_redirect(panel_url_for($logged['role']));
            }

            $access  = ($_GET['acceso'] ?? $_POST['acceso'] ?? 'bombero') === 'admin' ? 'admin' : 'bombero';
            $error   = null;
            $usuario = post_str('usuario', 60);

            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                csrf_verify();
                $result = attempt_login($usuario, (string) ($_POST['clave'] ?? ''), $access);

                if ($result['ok']) {
                    $account = $result['user'];
                    flash('ok', 'Bienvenido/a, ' . $account['full_name'] . '.');
                    if ((int) $account['must_change_password'] === 1) {
                        flash('warn', 'Por seguridad, define una contrasena nueva antes de continuar.');
                        sdb_redirect('perfil.php');
                    }
                    sdb_redirect(panel_url_for($account['role']));
                }

                $error = $result['error'];
            }

            $isAdmin = $access === 'admin';

            $data = [
                'access'  => $access,
                'isAdmin' => $isAdmin,
                'usuario' => $usuario,
                'error'   => $error,
                'flash'   => flash_pull(),
                // Ayuda de primer arranque: solo mientras el administrador
                // inicial nunca ingreso y solo en la pestana de administracion.
                'primerArranque' => $isAdmin && $this->primerArranque(),
            ];

            ob_start();
            extract($data, EXTR_SKIP);
            require APPPATH . 'Views/auth/login.php';

            return (string) ob_get_clean();
        } finally {
            // Igual que en Bomberos::index(): cerramos el lock de $_SESSION
            // antes de que CodeIgniter procese la respuesta.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    public function logout(): void
    {
        require_once APPPATH . 'Legacy/app/auth.php';

        send_security_headers();
        logout();

        // Sesion nueva y limpia, solo para transportar el mensaje de despedida.
        session_boot();
        session_regenerate_id(true);
        flash('ok', 'Cerraste sesion correctamente.');
        sdb_redirect('login.php');
    }

    private function primerArranque(): bool
    {
        return (int) db()->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND must_change_password = 1 AND last_login_at IS NULL"
        )->fetchColumn() > 0;
    }
}

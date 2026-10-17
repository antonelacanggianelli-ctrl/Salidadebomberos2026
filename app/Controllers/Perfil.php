<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Perfil propio de cada usuario, admin o bombero (Semana 4 del plan:
 * autogestion migrada a CodeIgniter 4).
 *
 * Reutiliza las funciones de sesion, CSRF, validacion de contrasenas y
 * bitacora de app/Legacy/app, igual que Auth y Bomberos. Cada usuario solo
 * puede cambiar sus datos de contacto y su contrasena: el nombre de usuario,
 * el rol y el estado de la cuenta los maneja la administracion.
 */
class Perfil extends Controller
{
    private array $user;
    private int $userId;

    public function index()
    {
        require_once APPPATH . 'Legacy/app/auth.php';
        require_once APPPATH . 'Legacy/app/repository.php';
        require_once APPPATH . 'Legacy/app/layout.php';

        send_security_headers();
        $this->user   = require_role('admin', 'bombero');
        $this->userId = (int) $this->user['id'];

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                $this->handlePost();
            }

            return $this->renderView();
        } finally {
            // Igual que Bomberos::index(): cerramos el lock de $_SESSION
            // antes de que CodeIgniter procese la respuesta.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /** Procesa la actualizacion de datos o de contrasena. Siempre redirige. */
    private function handlePost(): never
    {
        csrf_verify();
        $accion = post_str('accion', 20);

        if ($accion === 'datos') {
            $fullName = post_str('full_name', 90);
            $rank     = post_str('rank', 60);
            $phone    = post_str('phone', 40);

            if (mb_strlen($fullName) < 3) {
                flash('error', 'Escribe tu nombre y apellido.');
                sdb_redirect('perfil.php');
            }

            // El nombre de usuario y el estado de la cuenta solo los cambia la administracion.
            user_update($this->userId, [
                'username'  => $this->user['username'],
                'full_name' => $fullName,
                'rank'      => $rank,
                'phone'     => $phone,
                'active'    => (int) $this->user['active'],
            ]);
            audit('perfil_actualizado', '', $this->user);
            flash('ok', 'Tus datos fueron actualizados.');
            sdb_redirect('perfil.php');
        }

        if ($accion === 'clave') {
            $actual  = (string) ($_POST['actual'] ?? '');
            $nueva   = (string) ($_POST['nueva'] ?? '');
            $repetir = (string) ($_POST['repetir'] ?? '');

            if (!password_verify($actual, $this->user['password_hash'])) {
                flash('error', 'La contrasena actual no es correcta.');
                sdb_redirect('perfil.php');
            }
            if (($problem = password_problem($nueva, $repetir)) !== null) {
                flash('error', $problem);
                sdb_redirect('perfil.php');
            }
            if (password_verify($nueva, $this->user['password_hash'])) {
                flash('error', 'La contrasena nueva debe ser distinta de la actual.');
                sdb_redirect('perfil.php');
            }

            user_set_password($this->userId, $nueva, false);
            audit('clave_cambiada', '', $this->user);
            flash('ok', 'Contrasena actualizada. Usala en tu proximo ingreso.');
            sdb_redirect(panel_url_for($this->user['role']));
        }

        sdb_redirect('perfil.php');
    }

    /** Arma los datos y renderiza la vista del perfil. */
    private function renderView(): string
    {
        $data = [
            'user'       => $this->user,
            // La cantidad de alertas publicadas solo tiene sentido para bomberos.
            'misAlertas' => $this->user['role'] === 'bombero'
                ? count(alerts_list(['author' => $this->userId]))
                : null,
        ];

        ob_start();
        extract($data, EXTR_SKIP);
        require APPPATH . 'Views/perfil/index.php';

        return (string) ob_get_clean();
    }
}

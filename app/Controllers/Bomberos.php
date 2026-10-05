<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Panel del personal de bomberos (Semana 3 del plan: estructura base del
 * panel privado en CodeIgniter 4).
 *
 * Reutiliza las funciones de sesion, CSRF y permisos de app/Legacy/app,
 * asi que el login y las reglas de seguridad son exactamente las mismas
 * que en el resto del sitio: no hay dos sistemas de sesion conviviendo.
 * Lo que cambia es la organizacion del codigo: antes era un unico archivo
 * PHP con HTML mezclado; ahora es un Controller (logica) + Views (HTML).
 */
class Bomberos extends Controller
{
    private array $user;
    private int $userId;

    public function index()
    {
        require_once APPPATH . 'Legacy/app/auth.php';
        require_once APPPATH . 'Legacy/app/repository.php';
        require_once APPPATH . 'Legacy/app/layout.php';
        require_once APPPATH . 'Legacy/app/forms.php';

        send_security_headers();
        $this->user   = require_role('bombero');
        $this->userId = (int) $this->user['id'];

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                $this->handlePost();

                return '';
            }

            return $this->renderView();
        } finally {
            // Igual que LegacyApp::run(): la app usa $_SESSION directamente,
            // asi que cerramos el lock antes de que CodeIgniter procese la
            // respuesta y su propio servicio de sesion (ver app/Controllers/LegacyApp.php).
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /** Procesa alta/edicion, cambio de estado y borrado. Siempre redirige. */
    private function handlePost(): never
    {
        csrf_verify();
        $accion = post_str('accion', 20);
        $id     = (int) ($_POST['id'] ?? 0);

        if ($accion === 'guardar') {
            [$data, $errors] = alert_validate();

            if ($errors !== []) {
                stash_old($data);
                flash('error', implode(' ', $errors));
                sdb_redirect($id > 0 ? 'bomberos.php?vista=editar&id=' . $id : 'bomberos.php?vista=nueva');
            }

            if ($id > 0) {
                own_alert_or_fail($id, $this->userId);
                alert_update($id, $data);
                audit('alerta_editada', '#' . $id . ' ' . $data['title'], $this->user);
                flash('ok', 'Alerta #' . $id . ' actualizada.');
            } else {
                $id = alert_create($data, $this->userId);
                audit('alerta_creada', '#' . $id . ' ' . $data['title'], $this->user);
                flash('ok', 'Alerta #' . $id . ' publicada. Ya es visible en la portada.');
            }
            sdb_redirect('bomberos.php');
        }

        if ($accion === 'estado') {
            own_alert_or_fail($id, $this->userId);
            $estado = post_str('estado', 20);
            alert_set_status($id, $estado);
            audit('alerta_estado', '#' . $id . ' -> ' . $estado, $this->user);
            flash('ok', 'Alerta #' . $id . ' marcada como ' . $estado . '.');
            sdb_redirect('bomberos.php');
        }

        if ($accion === 'eliminar') {
            own_alert_or_fail($id, $this->userId);
            alert_delete($id);
            audit('alerta_eliminada', '#' . $id, $this->user);
            flash('ok', 'Alerta #' . $id . ' eliminada.');
            sdb_redirect('bomberos.php');
        }

        sdb_redirect('bomberos.php');
    }

    /** Arma los datos y renderiza la vista (lista o formulario). */
    private function renderView(): string
    {
        $vista      = $_GET['vista'] ?? 'lista';
        $misAlertas = alerts_list(['author' => $this->userId]);

        // La alerta a editar se resuelve antes de dibujar nada: si no le
        // pertenece hay que redirigir, y una redireccion despues de imprimir
        // la pagina no funciona.
        $editAlert = $vista === 'editar'
            ? own_alert_or_fail((int) ($_GET['id'] ?? 0), $this->userId)
            : null;

        $data = [
            'user'       => $this->user,
            'vista'      => $vista,
            'misAlertas' => $misAlertas,
            'activas'    => count(array_filter($misAlertas, static fn (array $a) => $a['status'] === 'activa')),
            'abiertas'   => count(array_filter($misAlertas, static fn (array $a) => $a['status'] !== 'cerrada')),
            'editAlert'  => $editAlert,
        ];

        ob_start();
        extract($data, EXTR_SKIP);

        if ($vista === 'nueva' || $vista === 'editar') {
            require APPPATH . 'Views/bomberos/formulario.php';
        } else {
            require APPPATH . 'Views/bomberos/lista.php';
        }

        return (string) ob_get_clean();
    }
}

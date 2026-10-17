<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Panel de administracion (Semana 4 del plan: gestion de usuarios migrada
 * a CodeIgniter 4).
 *
 * La migracion es por partes: la gestion del personal autorizado (listado,
 * alta, edicion, suspension y baja) ya vive en este Controller y en
 * Views/admin/. El resto de las secciones (resumen, alertas, contactos,
 * suscriptores y bitacora) se siguen atendiendo con app/Legacy/admin.php
 * hasta que se migren.
 */
class Admin extends Controller
{
    /** Vistas del panel que ya resuelve este Controller. */
    private const VISTAS_USUARIOS = ['usuarios', 'usuario_nuevo', 'usuario_editar'];

    /** Acciones POST que ya resuelve este Controller. */
    private const ACCIONES_USUARIOS = ['usuario_guardar', 'usuario_estado', 'usuario_eliminar'];

    private array $admin;
    private int $adminId;

    public function index()
    {
        require_once APPPATH . 'Legacy/app/auth.php';
        require_once APPPATH . 'Legacy/app/repository.php';
        require_once APPPATH . 'Legacy/app/layout.php';

        send_security_headers();
        $this->admin   = require_role('admin');
        $this->adminId = (int) $this->admin['id'];

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                if (in_array(post_str('accion', 30), self::ACCIONES_USUARIOS, true)) {
                    $this->handleUserPost();
                }

                return $this->legacy();
            }

            $vista = is_string($_GET['vista'] ?? null) ? $_GET['vista'] : 'resumen';
            if (in_array($vista, self::VISTAS_USUARIOS, true)) {
                return $this->renderUsers($vista);
            }

            return $this->legacy();
        } finally {
            // Igual que Bomberos::index(): cerramos el lock de $_SESSION
            // antes de que CodeIgniter procese la respuesta.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /** Secciones del panel que todavia no se migraron. */
    private function legacy(): string
    {
        ob_start();
        require APPPATH . 'Legacy/admin.php';

        return (string) ob_get_clean();
    }

    /* ------------------------------------------------------------ Acciones -- */

    /** Alta, edicion, suspension y baja de cuentas. Siempre redirige. */
    private function handleUserPost(): never
    {
        csrf_verify();
        $accion = post_str('accion', 30);
        $id     = (int) ($_POST['id'] ?? 0);

        if ($accion === 'usuario_guardar') {
            $this->saveUser($id);
        }

        $target = user_find($id);
        if ($target === null) {
            flash('error', 'El usuario indicado no existe.');
            sdb_redirect('admin.php?vista=usuarios');
        }

        if ($accion === 'usuario_estado') {
            $activar = post_str('valor', 5) === '1';
            if (!$activar && $id === $this->adminId) {
                flash('error', 'No puedes desactivar tu propia cuenta.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            if (!$activar && $target['role'] === 'admin' && $this->lastActiveAdmin($id)) {
                flash('error', 'Debe quedar al menos un administrador activo.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            user_set_active($id, $activar);
            audit($activar ? 'usuario_activado' : 'usuario_desactivado', $target['username'], $this->admin);
            flash('ok', 'Cuenta de ' . $target['full_name'] . ($activar ? ' habilitada.' : ' suspendida.'));
            sdb_redirect('admin.php?vista=usuarios');
        }

        // usuario_eliminar
        if ($id === $this->adminId) {
            flash('error', 'No puedes eliminar tu propia cuenta.');
            sdb_redirect('admin.php?vista=usuarios');
        }
        if ($target['role'] === 'admin' && $this->lastActiveAdmin($id)) {
            flash('error', 'Debe quedar al menos un administrador activo.');
            sdb_redirect('admin.php?vista=usuarios');
        }
        if (user_alert_count($id) > 0) {
            // Se suspende en lugar de borrar para no perder el historial de alertas.
            user_set_active($id, false);
            audit('usuario_desactivado', $target['username'] . ' (tiene alertas asociadas)', $this->admin);
            flash('warn', $target['full_name'] . ' tiene alertas publicadas, por eso se suspendio la cuenta en lugar de borrarla. Asi se conserva el historial.');
            sdb_redirect('admin.php?vista=usuarios');
        }
        user_delete($id);
        audit('usuario_eliminado', $target['username'], $this->admin);
        flash('ok', 'Se elimino la cuenta de ' . $target['full_name'] . '.');
        sdb_redirect('admin.php?vista=usuarios');
    }

    private function saveUser(int $id): never
    {
        $editing = $id > 0;
        [$data, $errors] = $this->userFormData($editing ? $id : null);
        $password   = (string) ($_POST['password'] ?? '');
        $confirm    = (string) ($_POST['password2'] ?? '');
        $mustChange = isset($_POST['must_change']);

        if (!$editing || $password !== '' || $confirm !== '') {
            if (($problem = password_problem($password, $confirm)) !== null) {
                $errors[] = $problem;
            }
        }

        if ($errors !== []) {
            stash_old($data);
            flash('error', implode(' ', $errors));
            sdb_redirect($editing
                ? 'admin.php?vista=usuario_editar&id=' . $id
                : 'admin.php?vista=usuario_nuevo');
        }

        if ($editing) {
            $target = user_find($id);
            if ($target === null) {
                flash('error', 'El usuario indicado no existe.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            if ($id === $this->adminId && $data['active'] === 0) {
                flash('error', 'No puedes desactivar tu propia cuenta.');
                sdb_redirect('admin.php?vista=usuario_editar&id=' . $id);
            }
            if ($target['role'] === 'admin' && $data['active'] === 0 && $this->lastActiveAdmin($id)) {
                flash('error', 'Debe quedar al menos un administrador activo.');
                sdb_redirect('admin.php?vista=usuario_editar&id=' . $id);
            }

            user_update($id, $data);
            if ($password !== '') {
                user_set_password($id, $password, $mustChange);
            }
            audit('usuario_editado', $data['username'], $this->admin);
            flash('ok', 'Datos de ' . $data['full_name'] . ' actualizados.');
        } else {
            user_create($data, $password, $this->adminId, $mustChange);
            audit('usuario_creado', $data['username'] . ' (' . $data['role'] . ')', $this->admin);
            flash('ok', 'Se autorizo a ' . $data['full_name'] . '. Entregale el usuario "' . $data['username'] . '" y su contrasena inicial.');
        }
        sdb_redirect('admin.php?vista=usuarios');
    }

    /** Datos del formulario de usuario, ya validados. */
    private function userFormData(?int $editingId): array
    {
        $errors = [];
        // El rol queda fijo despues de crear la cuenta. El selector se deshabilita
        // en la vista de edicion y, por eso, no viaja en el POST: debe recuperarse
        // desde la base para no convertir accidentalmente un admin en bombero.
        $existingRole = 'bombero';
        if ($editingId !== null) {
            $existing     = user_find($editingId);
            $existingRole = ($existing['role'] ?? '') === 'admin' ? 'admin' : 'bombero';
        }
        $data = [
            'username'  => mb_strtolower(post_str('username', 30)),
            'full_name' => post_str('full_name', 90),
            'role'      => $editingId === null
                ? (post_str('role', 10) === 'admin' ? 'admin' : 'bombero')
                : $existingRole,
            'rank'      => post_str('rank', 60),
            'phone'     => post_str('phone', 40),
            'active'    => isset($_POST['active']) ? 1 : 0,
        ];

        if (($problem = username_problem($data['username'])) !== null) {
            $errors[] = $problem;
        } elseif (username_taken($data['username'], $editingId)) {
            $errors[] = 'Ese nombre de usuario ya esta en uso.';
        }
        if (mb_strlen($data['full_name']) < 3) {
            $errors[] = 'Escribe el nombre y apellido del integrante.';
        }

        return [$data, $errors];
    }

    /** Impide dejar el sistema sin ningun administrador activo. */
    private function lastActiveAdmin(int $id): bool
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?");
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() === 0;
    }

    /* -------------------------------------------------------------- Vistas -- */

    /** Arma los datos y renderiza el listado o el formulario de usuarios. */
    private function renderUsers(string $vista): string
    {
        if ($vista === 'usuarios') {
            // La cantidad de alertas se calcula aca para que la vista no consulte la base.
            $conAlertas = static function (array $filas): array {
                foreach ($filas as &$u) {
                    $u['alertas'] = user_alert_count((int) $u['id']);
                }

                return $filas;
            };
            $data = [
                'admin'    => $this->admin,
                'adminId'  => $this->adminId,
                'bomberos' => $conAlertas(users_list('bombero')),
                'admins'   => $conAlertas(users_list('admin')),
            ];
            $view = 'Views/admin/usuarios.php';
        } else {
            $editing = $vista === 'usuario_editar';
            $target  = [
                'id' => 0, 'username' => '', 'full_name' => '', 'role' => 'bombero',
                'rank' => '', 'phone' => '', 'active' => 1, 'must_change_password' => 1,
            ];
            if ($editing) {
                // Se busca antes de dibujar nada: si no existe hay que poder redirigir.
                $target = user_find((int) ($_GET['id'] ?? 0));
                if ($target === null) {
                    flash('error', 'El usuario indicado no existe.');
                    sdb_redirect('admin.php?vista=usuarios');
                }
            }
            $old = pull_old();
            if ($old !== null) {
                $target = array_merge($target, $old);
            }
            $data = [
                'admin'   => $this->admin,
                'editing' => $editing,
                'target'  => $target,
            ];
            $view = 'Views/admin/usuario_form.php';
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require APPPATH . $view;

        return (string) ob_get_clean();
    }
}

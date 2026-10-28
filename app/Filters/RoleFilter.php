<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige sesion iniciada con alguno de los roles indicados antes de llegar
 * al Controller. Se aplica desde app/Config/Routes.php, por ejemplo:
 *
 *     $routes->get('admin.php', 'Admin::index', ['filter' => 'role:admin']);
 *
 * Usa la misma sesion y los mismos mensajes que require_role() de
 * app/Legacy/app/auth.php. Los Controllers siguen llamando a require_role()
 * para obtener el usuario, asi el permiso se valida dos veces: si alguien
 * olvida el filtro en una ruta, la pagina sigue protegida.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        require_once APPPATH . 'Legacy/app/auth.php';

        $roles = $arguments ?? [];
        $user  = current_user();

        if ($user === null) {
            flash('warn', 'Ingresa con tu usuario para continuar.');

            return $this->redirect('login.php');
        }
        if ($roles !== [] && !in_array($user['role'], $roles, true)) {
            flash('error', 'No tienes permisos para acceder a esa seccion.');

            return $this->redirect(panel_url_for($user['role']));
        }

        // Clave provisoria: hasta cambiarla solo se puede usar "Mi perfil".
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            $page = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
            if ($page !== 'perfil.php') {
                flash('warn', 'Por seguridad, define una contrasena nueva antes de continuar.');

                return $this->redirect('perfil.php');
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Redireccion relativa, igual que sdb_redirect(): baseURL apunta al
     * servidor de desarrollo de CodeIgniter y no sirve para XAMPP.
     */
    private function redirect(string $path): ResponseInterface
    {
        // La sesion de la app usa $_SESSION directamente: se guarda y libera
        // antes de que CodeIgniter procese la respuesta (ver LegacyApp::run()).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return service('response')->setStatusCode(302)->setHeader('Location', $path);
    }
}

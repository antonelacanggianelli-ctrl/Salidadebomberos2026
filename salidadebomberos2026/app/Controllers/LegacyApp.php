<?php

namespace App\Controllers;

/**
 * Puente temporal de migración: expone la aplicación existente mediante el
 * front controller de CodeIgniter, conservando visual y comportamiento.
 * Las páginas heredadas se irán separando en controladores y vistas CI4.
 */
class LegacyApp extends BaseController
{
    private function run(string $page): void
    {
        $allowed = [
            'index.php', 'login.php', 'logout.php', 'admin.php',
            'bomberos.php', 'perfil.php', 'api.php',
        ];
        if (!in_array($page, $allowed, true)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException($page);
        }

        $legacyRoot = APPPATH . 'Legacy' . DIRECTORY_SEPARATOR;
        try {
            require $legacyRoot . $page;
        } finally {
            // La aplicación heredada usa $_SESSION directamente. Cerramos el
            // lock antes de que CodeIgniter procese la respuesta y su sesión.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    public function index(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $page = basename($path);
        $this->run(in_array($page, [
            'login.php', 'logout.php', 'admin.php', 'bomberos.php',
            'perfil.php', 'api.php',
        ], true) ? $page : 'index.php');
    }

    public function login(): void
    {
        $this->run('login.php');
    }

    public function logout(): void
    {
        $this->run('logout.php');
    }

    public function admin(): void
    {
        $this->run('admin.php');
    }

    public function bomberos(): void
    {
        $this->run('bomberos.php');
    }

    public function perfil(): void
    {
        $this->run('perfil.php');
    }

    public function api(): void
    {
        $this->run('api.php');
    }
}

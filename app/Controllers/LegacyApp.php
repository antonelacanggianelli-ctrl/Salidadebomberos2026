<?php

namespace App\Controllers;

/**
 * Puente temporal de migración: expone la aplicación existente mediante el
 * front controller de CodeIgniter, conservando visual y comportamiento.
 * Las páginas heredadas se irán separando en controladores y vistas CI4.
 *
 * Ya migradas (no deben servirse desde acá, para que nadie las alcance
 * salteando los controladores y el filtro de rol): login.php, logout.php,
 * admin.php (Admin), bomberos.php (Bomberos) y perfil.php (Perfil).
 */
class LegacyApp extends BaseController
{
    private function run(string $page): void
    {
        $allowed = ['index.php', 'api.php', 'alerta.php'];
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
        $this->run(in_array($page, ['api.php', 'alerta.php'], true) ? $page : 'index.php');
    }

    public function alerta(): void
    {
        $this->run('alerta.php');
    }

    public function api(): void
    {
        // La respuesta es JSON y no debe quedar guardada en cachés compartidas.
        $this->response->setContentType('application/json', 'utf-8');
        $this->response->setHeader('Cache-Control', 'no-store');
        $this->run('api.php');
    }
}

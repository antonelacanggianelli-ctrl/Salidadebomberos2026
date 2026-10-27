<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'LegacyApp::index');
$routes->get('alerta.php', 'LegacyApp::alerta');
$routes->match(['get', 'post'], 'login.php', 'Auth::login');
$routes->get('logout.php', 'Auth::logout');
// Paneles privados: el filtro 'role' (app/Filters/RoleFilter.php) exige
// sesion con el rol indicado antes de llegar al Controller.
$routes->match(['get', 'post'], 'admin.php', 'Admin::index', ['filter' => 'role:admin']);
$routes->match(['get', 'post'], 'bomberos.php', 'Bomberos::index', ['filter' => 'role:bombero']);
$routes->match(['get', 'post'], 'perfil.php', 'Perfil::index', ['filter' => 'role:admin,bombero']);
$routes->get('api.php', 'LegacyApp::api');
// Sin ruta comodín: cualquier otra URL responde 404 (antes devolvía la portada con 200).
$routes->get('index.php', 'LegacyApp::index');

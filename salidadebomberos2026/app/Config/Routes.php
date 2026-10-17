<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'LegacyApp::index');
$routes->match(['get', 'post'], 'login.php', 'LegacyApp::login');
$routes->get('logout.php', 'LegacyApp::logout');
$routes->match(['get', 'post'], 'admin.php', 'LegacyApp::admin');
$routes->match(['get', 'post'], 'bomberos.php', 'LegacyApp::bomberos');
$routes->match(['get', 'post'], 'perfil.php', 'LegacyApp::perfil');
$routes->get('api.php', 'LegacyApp::api');
$routes->match(['get', 'post'], '(:any)', 'LegacyApp::index');

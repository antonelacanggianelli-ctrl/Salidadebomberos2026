<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Iniciales para el avatar del panel. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $last  = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

    return mb_strtoupper($first . $last);
}

/**
 * Cabecera comun de los paneles.
 *
 * @param array<string,string> $tabs  etiqueta => url
 */
function panel_head(string $title, array $user, array $tabs, string $activeTab): void
{
    $isAdmin = $user['role'] === 'admin';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex">
	<title><?= e($title) ?> | SalidadeBomberos!</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/app.css">
</head>
<body class="panel-body">
	<div class="panel-top">
		<div class="shell panel-top-inner">
			<a class="brand" href="<?= e(panel_url_for($user['role'])) ?>" style="color:#fff;">
				<span class="brand-mark"><img src="assets/img/logo-bomberos-chascomus.jpg" alt="Escudo de Bomberos Voluntarios de Chascomus"></span>
				<span class="brand-name">Salida<strong style="color:var(--amber);">deBomberos!</strong></span>
			</a>
			<div class="panel-user">
				<span class="tag <?= $isAdmin ? 'neutral' : 'st-activa' ?>" style="<?= $isAdmin ? 'background:var(--amber);color:var(--ink);' : '' ?>">
					<?= $isAdmin ? 'Administrador' : 'Bombero' ?>
				</span>
				<div class="who">
					<b><?= e($user['full_name']) ?></b>
					<small><?= e($user['username']) ?><?= $user['rank'] !== '' ? ' &middot; ' . e($user['rank']) : '' ?></small>
				</div>
				<span class="avatar" aria-hidden="true"><?= e(initials($user['full_name'])) ?></span>
				<a class="btn btn-sm btn-quiet" href="logout.php">Salir</a>
			</div>
		</div>
	</div>

	<nav class="panel-nav" aria-label="Secciones del panel">
		<div class="shell panel-nav-inner">
			<?php foreach ($tabs as $label => $url): ?>
				<a class="<?= $label === $activeTab ? 'on' : '' ?>" href="<?= e($url) ?>"><?= e($label) ?></a>
			<?php endforeach; ?>
			<a href="index.php" target="_blank" rel="noopener">Ver sitio publico &#8599;</a>
		</div>
	</nav>

	<main class="panel-main">
		<div class="shell">
    <?php
    $flash = flash_pull();
    if ($flash !== null) {
        echo '<div class="msg ' . e($flash[0]) . '"><div>' . e($flash[1]) . '</div></div>';
    }
    if ((int) ($user['must_change_password'] ?? 0) === 1 && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'perfil.php') {
        echo '<div class="msg warn"><div>Tu contrasena es provisoria. '
            . '<a href="perfil.php" style="font-weight:800;text-decoration:underline;">Cambiala ahora</a> para proteger tu cuenta.</div></div>';
    }
}

/** Pie comun de los paneles. */
function panel_foot(): void
{
    ?>
		</div>
	</main>
	<footer>
		<div class="shell footer-inner">
			<span><strong>SalidadeBomberos!</strong> &middot; Panel interno del cuartel.</span>
			<span>Toda accion queda registrada en la bitacora.</span>
		</div>
	</footer>
</body>
</html>
    <?php
}

/** Pestanas segun el rol. */
function panel_tabs(string $role): array
{
    if ($role === 'admin') {
        return [
            'Resumen'   => 'admin.php',
            'Bomberos'  => 'admin.php?vista=usuarios',
            'Alertas'   => 'admin.php?vista=alertas',
            'Contactos' => 'admin.php?vista=contactos',
            'Bitacora'  => 'admin.php?vista=bitacora',
            'Mi perfil' => 'perfil.php',
        ];
    }

    return [
        'Mis alertas' => 'bomberos.php',
        'Nueva alerta' => 'bomberos.php?vista=nueva',
        'Mi perfil'   => 'perfil.php',
    ];
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

send_security_headers();
session_boot();

// Ya autenticado: directo al panel que le corresponde.
$logged = current_user();
if ($logged !== null) {
    sdb_redirect(panel_url_for($logged['role']));
}

$access = ($_GET['acceso'] ?? $_POST['acceso'] ?? 'bombero') === 'admin' ? 'admin' : 'bombero';
$error  = null;
$user   = post_str('usuario', 60);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $result = attempt_login($user, (string) ($_POST['clave'] ?? ''), $access);

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
$flash   = flash_pull();
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex">
	<title>Acceso <?= $isAdmin ? 'administracion' : 'bomberos' ?> | SalidadeBomberos!</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/app.css">
</head>
<body>
	<div class="auth-page">
		<aside class="auth-aside">
			<a class="brand" href="index.php" style="color:#fff;">
				<span class="brand-mark"><img src="assets/img/logo-bomberos-chascomus.jpg" alt="Escudo de Bomberos Voluntarios de Chascomus"></span>
				<span class="brand-name" style="color:#fff;">Salida<strong style="color:var(--amber);">deBomberos!</strong></span>
			</a>
			<div>
				<p class="eyebrow">Acceso restringido</p>
				<h1>Centro de<br><span style="color:var(--red);">mando</span></h1>
				<p>Solo el personal autorizado publica alertas. Cada movimiento queda registrado con usuario, fecha y direccion de red.</p>
			</div>
			<ul class="auth-points">
				<li><span aria-hidden="true">01</span><div><b>Administracion</b>Da de alta a los bomberos, asigna sus claves y supervisa todo lo publicado.</div></li>
				<li><span aria-hidden="true">02</span><div><b>Bomberos</b>Cargan, actualizan y cierran las alertas que ve la comunidad.</div></li>
				<li><span aria-hidden="true">03</span><div><b>Trazabilidad</b>Bitacora de ingresos y cambios, con bloqueo tras varios intentos fallidos.</div></li>
			</ul>
		</aside>

		<main class="auth-main">
			<div class="auth-card">
				<div class="auth-tabs">
					<a class="<?= $isAdmin ? 'on' : '' ?>" href="login.php?acceso=admin">Administrador</a>
					<a class="<?= $isAdmin ? '' : 'on bombero' ?>" href="login.php?acceso=bombero">Bomberos</a>
				</div>

				<h2><?= $isAdmin ? 'Ingreso administrador' : 'Ingreso bomberos' ?></h2>
				<p class="lead">
					<?= $isAdmin
						? 'Panel de gestion del cuartel: usuarios, permisos, contactos y control de alertas.'
						: 'Ingresa con el usuario que te asigno la administracion para publicar y actualizar alertas.' ?>
				</p>

				<?php if ($error !== null): ?>
					<div class="msg error"><span aria-hidden="true">&#9888;</span><div><?= e($error) ?></div></div>
				<?php elseif ($flash !== null): ?>
					<div class="msg <?= e($flash[0]) ?>"><div><?= e($flash[1]) ?></div></div>
				<?php endif; ?>

				<form method="post" action="login.php" autocomplete="on">
					<?= sdb_csrf_field() ?>
					<input type="hidden" name="acceso" value="<?= e($access) ?>">

					<div class="field">
						<label for="usuario">Usuario</label>
						<input id="usuario" name="usuario" type="text" value="<?= e($user) ?>" required autofocus
						       autocomplete="username" maxlength="60" placeholder="<?= $isAdmin ? 'admin' : 'nombre.apellido' ?>">
					</div>

					<div class="field">
						<label for="clave">Contrasena</label>
						<input id="clave" name="clave" type="password" required autocomplete="current-password"
						       maxlength="200" placeholder="********">
					</div>

					<div class="form-actions">
						<button class="btn <?= $isAdmin ? 'btn-dark' : 'btn-primary' ?>" type="submit" style="width:100%;">
							Ingresar al panel <span aria-hidden="true">&rarr;</span>
						</button>
					</div>
				</form>

				<a class="auth-back" href="index.php">&larr; Volver a la pagina publica</a>

				<?php
				// Ayuda de primer arranque: solo mientras el administrador inicial nunca ingreso.
				$primerArranque = (int) db()->query(
					"SELECT COUNT(*) FROM users WHERE role = 'admin' AND must_change_password = 1 AND last_login_at IS NULL"
				)->fetchColumn() > 0;
				?>
				<?php if ($isAdmin && $primerArranque): ?>
					<div class="credential-box">
						<b>Primer ingreso:</b> usuario <code><?= e(BOOTSTRAP_ADMIN_USER) ?></code> y contrasena
						<code><?= e(BOOTSTRAP_ADMIN_PASS) ?></code>. El sistema te pedira cambiarla de inmediato.
					</div>
				<?php endif; ?>
			</div>
		</main>
	</div>
</body>
</html>

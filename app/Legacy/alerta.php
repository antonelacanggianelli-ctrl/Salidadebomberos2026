<?php
declare(strict_types=1);

/** Detalle publico de una alerta individual. */

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/repository.php';

send_security_headers();
session_boot();

$id    = (int) ($_GET['id'] ?? 0);
$alert = $id > 0 ? alert_find($id) : null;
$user  = current_user();

// Una alerta no publicada solo la puede ver el personal autenticado
// (el mismo control que aplica en el portal y en la API).
if ($alert !== null && (int) $alert['published'] !== 1 && $user === null) {
    $alert = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Detalle de alerta - SalidadeBomberos!">
	<title><?= $alert !== null ? e($alert['title']) . ' | ' : '' ?>SalidadeBomberos!</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/app.css">
</head>
<body>
	<header>
		<div class="shell nav">
			<a class="brand" href="index.php" aria-label="SalidadeBomberos inicio">
				<span class="brand-mark"><img src="assets/img/logo-bomberos-chascomus.jpg" alt="Escudo de Bomberos Voluntarios de Chascomus"></span>
				<span class="brand-name">Salida<strong>deBomberos!</strong></span>
			</a>
			<nav class="main" aria-label="Navegacion principal">
				<a href="index.php#alertas">Alertas</a>
				<a href="index.php#protocolo">Protocolo</a>
				<a href="index.php#contacto">Contactos</a>
			</nav>
			<div class="nav-actions">
				<?php if ($user !== null): ?>
					<a class="nav-cta ghost" href="<?= e(panel_url_for($user['role'])) ?>">Mi panel</a>
					<a class="nav-cta" href="logout.php">Salir</a>
				<?php else: ?>
					<a class="nav-cta ghost" href="login.php?acceso=bombero">Acceso bomberos</a>
					<a class="nav-cta" href="login.php?acceso=admin">Administracion</a>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<main>
		<section class="section" id="detalle">
			<div class="shell" style="max-width:820px;">
				<p class="section-kicker"><a href="index.php#alertas">&larr; Volver a todas las alertas</a></p>

				<?php if ($alert === null): ?>
					<div class="empty-state">
						<strong>Alerta no encontrada</strong>
						No existe o ya no esta disponible. Puede que haya sido eliminada o que el enlace este vencido.
					</div>
				<?php else: ?>
					<article class="alert-card <?= e(severity_class($alert['severity'])) ?>" style="margin-top:18px;">
						<div class="bar" aria-hidden="true"></div>
						<div class="alert-body">
							<div class="alert-top">
								<h1 style="font-family:var(--font-display, inherit); font-size:2rem; margin:0;"><?= e($alert['title']) ?></h1>
								<span class="tag <?= e(status_class($alert['status'])) ?>"><?= e($alert['status']) ?></span>
								<span class="tag <?= e(severity_class($alert['severity'])) ?>">Severidad <?= e($alert['severity']) ?></span>
								<span class="tag neutral"><?= e($alert['type']) ?></span>
							</div>

							<p class="alert-desc"><?= nl2br(e($alert['description'])) ?></p>

							<?php if ($alert['instructions'] !== ''): ?>
								<p class="alert-instructions"><b>Indicaciones:</b> <?= nl2br(e($alert['instructions'])) ?></p>
							<?php endif; ?>

							<div class="alert-meta">
								<span>Zona: <b><?= e($alert['zone'] !== '' ? $alert['zone'] : 'Sin especificar') ?></b></span>
								<span>Ubicacion: <b><?= e($alert['location']) ?></b></span>
								<?php if ($alert['meeting_point'] !== ''): ?>
									<span>Punto de reunion: <b><?= e($alert['meeting_point']) ?></b></span>
								<?php endif; ?>
								<span>Reporta: <b><?= e($alert['author_name']) ?></b><?= $alert['author_rank'] !== '' ? ' (' . e($alert['author_rank']) . ')' : '' ?></span>
								<span>Publicada: <b><?= e(fmt_dt($alert['created_at'])) ?></b></span>
								<?php if ($alert['updated_at'] !== $alert['created_at']): ?>
									<span>Actualizada: <b><?= e(fmt_dt($alert['updated_at'])) ?></b></span>
								<?php endif; ?>
								<?php if ($alert['closed_at'] !== null): ?>
									<span>Cerrada: <b><?= e(fmt_dt($alert['closed_at'])) ?></b></span>
								<?php endif; ?>
							</div>

							<?php if (!(bool) $alert['published']): ?>
								<p class="hint" style="margin-top:14px;">Esta alerta no esta publicada al publico general; solo la ve el personal autenticado.</p>
							<?php endif; ?>
						</div>
					</article>
				<?php endif; ?>
			</div>
		</section>
	</main>

	<footer class="site-foot">
		<div class="shell">
			<span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; <?= e(APP_STATION) ?></span>
		</div>
	</footer>
</body>
</html>

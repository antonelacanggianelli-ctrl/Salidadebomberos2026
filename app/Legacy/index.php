<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/repository.php';

send_security_headers();
session_boot();

// Alta de un suscriptor para recibir avisos por SMS/WhatsApp (envio manual, ver panel de administracion).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post_str('accion', 20) === 'suscribirse') {
    csrf_verify();

    if (post_str('web', 20) !== '') {
        // Campo trampa para bots: si viene completo, se ignora en silencio.
        sdb_redirect('index.php#suscripcion');
    }

    $name  = post_str('name', 80);
    $phone = normalize_phone((string) ($_POST['phone'] ?? ''));

    if (($problem = phone_problem($phone)) !== null) {
        flash('error', $problem);
    } elseif (subscriber_phone_taken($phone)) {
        flash('warn', 'Ese numero ya esta anotado para recibir alertas.');
    } else {
        subscriber_create($name, $phone);
        flash('ok', 'Listo, quedaste anotado/a. El cuartel te va a avisar a este numero ante una alerta.');
    }
    sdb_redirect('index.php#suscripcion');
}

$subscribeFlash = flash_pull();
$user       = current_user();

// Filtros del portal publico (semana 5 del plan: listado, detalle y filtros).
// Se leen por GET para que la URL sea compartible/bookmarkeable.
$filterType   = post_or_get_str('tipo', 60);
$filterZone   = post_or_get_str('zona', 60);
$filterStatus = post_or_get_str('estado', 20);
if (!in_array($filterType, ALERT_TYPES, true)) {
    $filterType = '';
}
if (!in_array($filterZone, ALERT_ZONES, true)) {
    $filterZone = '';
}
if (!in_array($filterStatus, ALERT_STATUSES, true)) {
    $filterStatus = '';
}
$hasFilters = $filterType !== '' || $filterZone !== '' || $filterStatus !== '';

if ($hasFilters) {
    $filters = ['published_only' => true];
    if ($filterType !== '') {
        $filters['type'] = $filterType;
    }
    if ($filterZone !== '') {
        $filters['zone'] = $filterZone;
    }
    if ($filterStatus !== '') {
        $filters['status'] = $filterStatus;
    }
    $alerts = alerts_list($filters, 100);
    if ($filterStatus === '') {
        // Sin filtro de estado explicito, el listado sigue mostrando por
        // defecto solo lo vigente (igual que el feed sin filtrar).
        $alerts = array_values(array_filter($alerts, static fn (array $a) => $a['status'] !== 'cerrada'));
    }
} else {
    $alerts = alerts_public();
}

// El resumen del estado general del edificio SIEMPRE refleja todas las
// alertas vigentes, sin importar los filtros que este mirando la persona.
$summary    = alerts_summary(alerts_public());
$closed     = alerts_recently_closed(4);
$contacts   = contacts_all();

$activas    = array_values(array_filter($alerts, static fn (array $a) => $a['status'] === 'activa'));
$enAlarma   = $summary['en_alarma'];

$meetingPoint = 'Patio central';
foreach ($contacts as $contact) {
    if ($contact['tone'] === 'blue') {
        $meetingPoint = $contact['number'];
        break;
    }
}
if ($activas !== [] && $activas[0]['meeting_point'] !== '') {
    $meetingPoint = $activas[0]['meeting_point'];
}

$statusTitle = $summary['titulo'];
$statusHint  = $summary['detalle'];
$mainExit    = $enAlarma ? 'Sigue la ruta indicada' : 'Despejada';
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="SalidadeBomberos! - alertas en vivo, guia rapida de evacuacion y respuesta ante emergencias.">
	<title><?= $enAlarma ? '(' . count($activas) . ') ' : '' ?>SalidadeBomberos! | Respuesta y evacuacion</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/app.css">
</head>
<body>
	<header>
		<div class="shell nav">
			<a class="brand" href="#inicio" aria-label="SalidadeBomberos inicio">
				<span class="brand-mark"><img src="assets/img/logo-bomberos-chascomus.jpg" alt="Escudo de Bomberos Voluntarios de Chascomus"></span>
				<span class="brand-name">Salida<strong>deBomberos!</strong></span>
			</a>
			<nav class="main" aria-label="Navegacion principal">
				<a href="#alertas">Alertas</a>
				<a href="#protocolo">Protocolo</a>
				<a href="#contacto">Contactos</a>
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
		<section class="hero" id="inicio">
			<div class="shell hero-grid">
				<div>
					<p class="eyebrow">Guia de respuesta ante emergencias</p>
					<h1>Actua rapido.<br><span>Sal a salvo.</span></h1>
					<p class="hero-copy">Informacion clara para tomar buenas decisiones cuando cada segundo cuenta. Manten la calma, sigue la ruta señalizada y llega al punto de encuentro.</p>
					<div class="actions">
						<a class="btn btn-primary" href="#alertas">Ver alertas en vivo <span aria-hidden="true">&rarr;</span></a>
						<a class="btn btn-quiet" href="#suscripcion">Recibir SMS con alerta en mi celular</a>
					</div>
				</div>
				<aside class="hero-panel" aria-label="Estado de la emergencia">
					<div class="panel-label" style="justify-content:flex-end;">
						<span><?= e(strtoupper(fmt_dt(now_utc()))) ?></span>
					</div>
					<div class="status">
						<span class="status-icon<?= $enAlarma ? ' alarma' : '' ?>" aria-hidden="true"><?= $enAlarma ? '!' : '&check;' ?></span>
						<div>
							<strong data-live="status-title"><?= e($statusTitle) ?></strong>
							<small data-live="status-hint"><?= e($statusHint) ?></small>
						</div>
					</div>
					<div class="panel-detail">
						<div><small>Salida principal</small><b><?= e($mainExit) ?></b></div>
						<div><small>Punto de reunion</small><b><?= e($meetingPoint) ?></b></div>
					</div>
				</aside>
			</div>
		</section>

		<section class="section" id="suscripcion" style="background:var(--ink);color:#fff;padding:64px 0;">
			<div class="shell" style="max-width:640px;">
				<p class="section-kicker" style="color:var(--amber);">Alertas por SMS</p>
				<h2 style="color:#fff;font-size:clamp(2.1rem,4vw,3rem);">Recibi el aviso<br>en tu celular</h2>
				<p class="section-intro" style="color:#cad1d0;max-width:520px;">Dejanos tu numero y el cuartel te va a avisar por mensaje de texto o WhatsApp cuando se publique una alerta cerca tuyo.</p>

				<?php if ($subscribeFlash !== null): ?>
					<div class="msg <?= e($subscribeFlash[0]) ?>" style="margin-top:22px;"><div><?= e($subscribeFlash[1]) ?></div></div>
				<?php endif; ?>

				<form method="post" action="index.php#suscripcion" style="margin-top:22px;max-width:420px;">
					<?= sdb_csrf_field() ?>
					<input type="hidden" name="accion" value="suscribirse">
					<input type="text" name="web" value="" autocomplete="off" tabindex="-1" aria-hidden="true"
					       style="position:absolute;left:-9999px;width:1px;height:1px;">

					<div class="field">
						<label for="sub_name" style="color:#cad1d0;">Nombre (opcional)</label>
						<input id="sub_name" name="name" type="text" maxlength="80" placeholder="Como te avisamos">
					</div>
					<div class="field">
						<label for="sub_phone" style="color:#cad1d0;">Numero de celular</label>
						<input id="sub_phone" name="phone" type="tel" required maxlength="24" placeholder="Ej.: 2241 555555">
					</div>
					<div class="form-actions">
						<button class="btn btn-primary" type="submit">Anotarme para recibir alertas</button>
					</div>
				</form>

				<div id="pushBox" style="margin-top:28px;padding-top:22px;border-top:1px solid rgba(255,255,255,.15);" data-fcm-configured="<?= fcm_client_ready() ? '1' : '0' ?>" data-zone-select-name="zona">
					<p class="section-kicker" style="color:var(--amber);">Notificaciones push</p>
					<p style="color:#cad1d0;max-width:520px;margin:6px 0 14px;">Recibi el aviso al instante en este navegador, ademas o en vez del SMS.</p>
					<div class="field" style="max-width:280px;">
						<label for="push_zone" style="color:#cad1d0;">Avisarme de esta zona</label>
						<select id="push_zone">
							<option value="">Todas las zonas</option>
							<?php foreach (ALERT_ZONES as $zone): ?>
								<option value="<?= e($zone) ?>"><?= e($zone) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<button id="pushEnableBtn" class="btn btn-quiet" type="button">Activar notificaciones en este dispositivo</button>
					<p id="pushStatus" class="hint" style="color:#cad1d0;margin-top:10px;"></p>
				</div>
			</div>
		</section>

		<section class="section" id="alertas">
			<div class="shell">
				<div class="section-head">
					<div>
						<p class="section-kicker">Reportes del cuerpo de bomberos</p>
						<h2>Alertas<br>en vivo</h2>
					</div>
					<p class="section-intro">Publicadas y actualizadas por el personal autorizado del cuartel. La pagina se refresca sola cada 60 segundos.</p>
				</div>

				<form class="alert-filters" method="get" action="index.php#alertas" aria-label="Filtrar alertas">
					<div class="field">
						<label for="f-tipo">Tipo</label>
						<select id="f-tipo" name="tipo" onchange="this.form.submit()">
							<option value="">Todos</option>
							<?php foreach (ALERT_TYPES as $type): ?>
								<option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e($type) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field">
						<label for="f-zona">Zona</label>
						<select id="f-zona" name="zona" onchange="this.form.submit()">
							<option value="">Todas</option>
							<?php foreach (ALERT_ZONES as $zone): ?>
								<option value="<?= e($zone) ?>" <?= $filterZone === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field">
						<label for="f-estado">Estado</label>
						<select id="f-estado" name="estado" onchange="this.form.submit()">
							<option value="">Vigentes</option>
							<?php foreach (ALERT_STATUSES as $st): ?>
								<option value="<?= e($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<noscript><button class="btn btn-sm btn-line" type="submit">Filtrar</button></noscript>
					<?php if ($hasFilters): ?>
						<a class="btn btn-sm btn-quiet" href="index.php#alertas">Quitar filtros</a>
					<?php endif; ?>
				</form>

				<div class="alert-feed" id="alertFeed">
					<?php if ($alerts === []): ?>
						<div class="empty-state">
							<strong>Sin alertas activas</strong>
							No hay incidentes reportados en este momento. Revisa el protocolo y manten los accesos despejados.
						</div>
					<?php else: ?>
						<?php foreach ($alerts as $alert): ?>
							<article class="alert-card <?= e(severity_class($alert['severity'])) ?>">
								<div class="bar" aria-hidden="true"></div>
								<div class="alert-body">
									<div class="alert-top">
										<h3><?= e($alert['title']) ?></h3>
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
											<span>Actualizada <b><?= e(fmt_ago($alert['updated_at'])) ?></b></span>
										<?php endif; ?>
									</div>
									<div class="alert-actions">
										<a class="btn btn-sm btn-line" href="alerta.php?id=<?= (int) $alert['id'] ?>">Ver detalle &rarr;</a>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<?php if ($closed !== []): ?>
					<div class="section-head" style="margin:46px 0 18px;">
						<div><p class="section-kicker">Historial</p><h2 style="font-size:2rem;">Cerradas recientemente</h2></div>
					</div>
					<div class="table-wrap">
						<table class="grid">
							<thead><tr><th>Incidente</th><th>Tipo</th><th>Ubicacion</th><th>Cierre</th></tr></thead>
							<tbody>
								<?php foreach ($closed as $item): ?>
									<tr>
										<td><b><?= e($item['title']) ?></b></td>
										<td class="muted"><?= e($item['type']) ?></td>
										<td class="muted"><?= e($item['location']) ?></td>
										<td class="muted"><?= e(fmt_dt($item['closed_at'] ?? $item['updated_at'])) ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="section" id="protocolo" style="background:var(--paper);">
			<div class="shell">
				<div class="section-head">
					<div>
						<p class="section-kicker">Respuesta en tres pasos</p>
						<h2>Tu ruta de<br>seguridad</h2>
					</div>
					<p class="section-intro">Ante humo, fuego o una alarma, no improvises. Sigue estas indicaciones y ayuda a quienes lo necesiten.</p>
				</div>
				<div class="steps">
					<article class="step"><span class="step-number">01</span><h3>Manten la calma</h3><p>Interrumpe la actividad. No corras, no empujes y escucha las instrucciones del personal responsable.</p></article>
					<article class="step"><span class="step-number">02</span><h3>Busca la salida</h3><p>Avanza por la ruta señalizada mas cercana. No uses ascensores y mantente alejado del humo.</p></article>
					<article class="step"><span class="step-number">03</span><h3>Reunete</h3><p>Dirigete a <?= e($meetingPoint) ?>. No regreses al edificio hasta que los servicios de emergencia lo autoricen.</p></article>
				</div>
			</div>
		</section>

		<div class="alert-band">
			<div class="shell alert-inner">
				<span class="alert-symbol" aria-hidden="true">!</span>
				<div>
					<strong>Si ves humo, avisa</strong>
					<p>Comunica tu ubicacion exacta y sigue las indicaciones del equipo de emergencia.</p>
				</div>
				<a class="btn btn-dark" href="#contacto">Contactos utiles <span aria-hidden="true">&rarr;</span></a>
			</div>
		</div>

		<section class="section" id="contacto">
			<div class="shell contact-grid">
				<div>
					<p class="section-kicker">Numeros y ubicaciones</p>
					<h2>Siempre a mano</h2>
					<p class="contact-copy">Guarda esta pagina para consultar los datos esenciales durante una evacuacion. En una emergencia real, prioriza siempre tu seguridad y la de quienes te rodean.</p>
				</div>
				<div class="contact-list">
					<?php foreach ($contacts as $contact): ?>
						<div class="contact-card <?= e($contact['tone']) ?>">
							<div>
								<small><?= e($contact['label']) ?></small>
								<b><?= $contact['tone'] === 'blue' ? 'Ubicacion designada' : 'Llamada inmediata' ?></b>
							</div>
							<strong><?= e($contact['number']) ?></strong>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	</main>

	<footer>
		<div class="shell footer-inner">
			<span><strong>SalidadeBomberos!</strong> &middot; Informacion para actuar mejor.</span>
			<span>
				<?php if ($user !== null): ?>
					Sesion de <strong><?= e($user['full_name']) ?></strong> &middot; <a href="<?= e(panel_url_for($user['role'])) ?>">Ir al panel</a>
				<?php else: ?>
					<a href="login.php?acceso=bombero">Acceso bomberos</a> &middot; <a href="login.php?acceso=admin">Administracion</a>
				<?php endif; ?>
			</span>
		</div>
	</footer>

	<div class="notice" id="notice" role="status"></div>

	<script>
		(function () {
			const feedCount = <?= count($alerts) ?>;
			const notice = document.getElementById('notice');

			function showNotice(text) {
				notice.textContent = text;
				notice.classList.add('show');
				window.setTimeout(() => notice.classList.remove('show'), 6000);
			}

			// Refresco automatico: si cambian las alertas, recarga la portada.
			window.setInterval(async () => {
				try {
					const res = await fetch('api.php?recurso=alertas', { cache: 'no-store' });
					if (!res.ok) return;
					const data = await res.json();
					const title = document.querySelector('[data-live="status-title"]');
					const hint = document.querySelector('[data-live="status-hint"]');
					if (title) title.textContent = data.resumen.titulo;
					if (hint) hint.textContent = data.resumen.detalle;
					if (data.alertas.length !== feedCount) {
						showNotice('Hay novedades en el tablero de alertas. Actualizando...');
						window.setTimeout(() => window.location.reload(), 2500);
					}
				} catch (err) {
					/* sin conexion: la pagina sigue mostrando la ultima informacion cargada */
				}
			}, 60000);
		})();
	</script>
</body>
</html>

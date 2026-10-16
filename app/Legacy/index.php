<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/repository.php';

send_security_headers();
session_boot();

$user = current_user();

// Filtros del portal público para las alertas
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
        $alerts = array_values(array_filter($alerts, static fn (array $a) => $a['status'] !== 'cerrada'));
    }
} else {
    $alerts = alerts_public();
}

$summary  = alerts_summary(alerts_public());
$closed   = alerts_recently_closed(4);
$contacts = contacts_all();

$activas  = array_values(array_filter($alerts, static fn (array $a) => $a['status'] === 'activa'));
$enAlarma = $summary['en_alarma'];

$meetingPoint = 'Cuartel Central (Av. Lastra y Soler)';
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
$mainExit    = $enAlarma ? 'Sigue la ruta de evacuacion' : 'Operatividad normal';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sociedad de Bomberos Voluntarios de Chascomús - Alertas de emergencia en tiempo real, teléfonos útiles y prevención de siniestros.">
    <title><?= $enAlarma ? '(' . count($activas) . ') ' : '' ?>Bomberos Voluntarios de Chascomús | Centro de Alertas y Prevencion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css?v=4">
</head>
<body>
    <header>
        <div class="shell nav">
            <a class="brand" href="#inicio" aria-label="Bomberos Voluntarios de Chascomus inicio">
                <span class="brand-mark"><img src="assets/img/logo-bomberos-chascomus.jpg" alt="Escudo Bomberos Voluntarios de Chascomus"></span>
                <span class="brand-name">Bomberos<strong>Chascomús</strong></span>
            </a>
            <nav class="main" aria-label="Navegacion principal">
                <a href="#" id="navAlertasBtn">Alertas en Vivo</a>
                <a href="#protocolo">¿Que hacer ante un siniestro?</a>
                <a href="#contacto">Telefonos Utiles</a>
            </nav>
            <div class="nav-actions">
                <?php if ($user !== null): ?>
                    <a class="nav-cta ghost" href="<?= e(panel_url_for($user['role'])) ?>">Mi Panel</a>
                    <a class="nav-cta" href="logout.php">Salir</a>
                <?php else: ?>
                    <a class="nav-cta ghost" href="login.php?acceso=bombero">Acceso Bomberos</a>
                    <a class="nav-cta" href="login.php?acceso=admin">Administracion</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- HERO PRINCIPAL -->
        <section class="hero" id="inicio">
            <div class="shell hero-grid">
                <div>
                    <p class="eyebrow">Sociedad de Bomberos Voluntarios de Chascomús</p>
                    <h1>Prevencion.<br><span>Servicio y Accion.</span></h1>
                    <p class="hero-copy">Centro de informacion publica en tiempo real ante incendios, rescates y siniestros en la ciudad de Chascomús. Manten la calma y sigue las recomendaciones oficiales.</p>
                    <div class="actions">
                        <button class="btn btn-primary" id="openAlertasModalBtn" type="button">
                            Ver alertas en vivo <span aria-hidden="true">&rarr;</span>
                        </button>
                        <a class="btn btn-quiet" href="#contacto">Emergencias: 100 / 42-2222</a>
                    </div>
                </div>
                <aside class="hero-panel" aria-label="Estado operativo del cuartel">
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
                        <div><small>Estado operativo</small><b><?= e($mainExit) ?></b></div>
                        <div><small>Punto de concentracion</small><b><?= e($meetingPoint) ?></b></div>
                    </div>
                </aside>
            </div>
        </section>

        <!-- VENTANA EMERGENTE (MODAL) CON ALERTAS EN VIVO -->
        <div class="modal-overlay" id="alertasModal">
            <div class="modal-content">
                <button class="modal-close" id="closeAlertasModalBtn" type="button">&times;</button>
                
                <div class="section-head" style="margin-bottom:20px;">
                    <div>
                        <p class="section-kicker">Reportes oficiales en tiempo real</p>
                        <h2 style="font-size:2.2rem;">Alertas Activas en Chascomús</h2>
                    </div>
                </div>

                <form class="alert-filters" method="get" action="index.php" aria-label="Filtrar alertas" style="margin-bottom:20px;">
                    <input type="hidden" name="openModal" value="1">
                    <div class="field-row">
                        <div class="field">
                            <label for="f-tipo">Tipo de siniestro</label>
                            <select id="f-tipo" name="tipo" onchange="this.form.submit()">
                                <option value="">Todos los siniestros</option>
                                <?php foreach (ALERT_TYPES as $type): ?>
                                    <option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="f-zona">Zona de la ciudad</label>
                            <select id="f-zona" name="zona" onchange="this.form.submit()">
                                <option value="">Todas las zonas</option>
                                <?php foreach (ALERT_ZONES as $zone): ?>
                                    <option value="<?= e($zone) ?>" <?= $filterZone === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="f-estado">Estado del servicio</label>
                            <select id="f-estado" name="estado" onchange="this.form.submit()">
                                <option value="">Vigentes</option>
                                <?php foreach (ALERT_STATUSES as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($hasFilters): ?>
                        <a class="btn btn-sm btn-quiet" href="index.php?openModal=1" style="color:var(--ink);border-color:var(--line);">Limpiar filtros</a>
                    <?php endif; ?>
                </form>

                <div class="alert-feed" id="alertFeed">
                    <?php if ($alerts === []): ?>
                        <div class="empty-state">
                            <strong>Sin alertas operativas activas</strong>
                            No hay intervenciones o emergencias reportadas en la ciudad en este momento. Manten libres las vias de circulacion para las unidades de rescate.
                        </div>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <article class="alert-card <?= e(severity_class($alert['severity'])) ?>">
                                <div class="bar" aria-hidden="true"></div>
                                <div class="alert-body">
                                    <div class="alert-top">
                                        <h3><?= e($alert['title']) ?></h3>
                                        <span class="tag <?= e(status_class($alert['status'])) ?>"><?= e($alert['status']) ?></span>
                                        <span class="tag <?= e(severity_class($alert['severity'])) ?>">Nivel <?= e($alert['severity']) ?></span>
                                        <span class="tag neutral"><?= e($alert['type']) ?></span>
                                    </div>
                                    <p class="alert-desc"><?= nl2br(e($alert['description'])) ?></p>
                                    <?php if ($alert['instructions'] !== ''): ?>
                                        <p class="alert-instructions"><b>Instrucciones a la poblacion:</b> <?= nl2br(e($alert['instructions'])) ?></p>
                                    <?php endif; ?>
                                    <div class="alert-meta">
                                        <span>Zona: <b><?= e($alert['zone'] !== '' ? $alert['zone'] : 'Chascomús Centro / Periurbano') ?></b></span>
                                        <span>Ubicacion: <b><?= e($alert['location']) ?></b></span>
                                        <?php if ($alert['meeting_point'] !== ''): ?>
                                            <span>Punto de reunion: <b><?= e($alert['meeting_point']) ?></b></span>
                                        <?php endif; ?>
                                        <span>Oficial a cargo: <b><?= e($alert['author_name']) ?></b><?= $alert['author_rank'] !== '' ? ' (' . e($alert['author_rank']) . ')' : '' ?></span>
                                        <span>Hora de reporte: <b><?= e(fmt_dt($alert['created_at'])) ?></b></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($closed !== []): ?>
                    <div class="section-head" style="margin:30px 0 14px;">
                        <div><p class="section-kicker">Historial reciente</p><h3 style="font-size:1.5rem;">Intervenciones finalizadas</h3></div>
                    </div>
                    <div class="table-wrap">
                        <table class="grid">
                            <thead><tr><th>Siniestro</th><th>Tipo</th><th>Ubicacion</th><th>Hora de Cierre</th></tr></thead>
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
        </div>

        <!-- GUÍA DE ACTUACIÓN ANTE INCENDIOS Y SINIESTROS -->
        <section class="section" id="protocolo" style="background:var(--paper);">
            <div class="shell">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Guia de actuacion ciudadana</p>
                        <h2>¿Que hacer ante<br>un incendio o siniestro?</h2>
                    </div>
                    <p class="section-intro">La rapidez de respuesta y la calma son indispensables para cuidar tu vida y la de tu familia mientras las dotaciones acuden al lugar.</p>
                </div>
                <div class="steps">
                    <article class="step">
                        <span class="step-number">01</span>
                        <h3>Conserva la calma y da aviso</h3>
                        <p>Evacua la zona afectada de inmediato. Llama urgente a la central de Bomberos (<b>100</b> o <b>42-2222</b>) detallando la direccion exacta del siniestro.</p>
                    </article>
                    <article class="step">
                        <span class="step-number">02</span>
                        <h3>Corta suministros y evacua</h3>
                        <p>Si es seguro hacerlo, interrumpe el gas y la electricidad. No intentes apagar fuegos de gran magnitud sin equipamiento adecuado. Avance a nivel del suelo si hay humo.</p>
                    </article>
                    <article class="step">
                        <span class="step-number">03</span>
                        <h3>Facilita el acceso</h3>
                        <p>Espera a la dotacion en un lugar seguro fuera de la vivienda o edificio. Manten calles y accesos libres para las autobombas y ambulancias.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- BANDA DE ADVERTENCIA / CONSEJO -->
        <div class="alert-band">
            <div class="shell alert-inner">
                <span class="alert-symbol" aria-hidden="true">!</span>
                <div>
                    <strong>¿Ves humo o sospechas de una quema no autorizada?</strong>
                    <p>En epoca de sequia o altos vientos, las quemas forestales en la zona de lagunas o campos representan un gran peligro. Avisanos de inmediato.</p>
                </div>
                <a class="btn btn-dark" href="#contacto">Llamar a la Central <span aria-hidden="true">&rarr;</span></a>
            </div>
        </div>

        <!-- TELÉFONOS ÚTILES Y CONTACTO DE CHASCOMÚS -->
        <section class="section" id="contacto">
            <div class="shell contact-grid">
                <div>
                    <p class="section-kicker">Telefonos de emergencia y seguridad</p>
                    <h2>Siempre a mano</h2>
                    <p class="contact-copy">Guarda estos numeros clave para comunicarte rapidamente con las fuerzas de seguridad, salud y bomberos de la ciudad de Chascomús.</p>
                </div>
                <div class="contact-list">
                    <div class="contact-card amber">
                        <div>
                            <small>Bomberos Voluntarios Chascomús</small>
                            <b>Linea de Emergencia Directa</b>
                        </div>
                        <strong>100 / (02241) 42-2222</strong>
                    </div>
                    <div class="contact-card red">
                        <div>
                            <small>Hospital Municipal San Vicente de Paul</small>
                            <b>Urgencias Médicas / Ambulancia</b>
                        </div>
                        <strong>107 / (02241) 42-2231</strong>
                    </div>
                    <div class="contact-card blue">
                        <div>
                            <small>Estacion de Policia Comunitaria Chascomús</small>
                            <b>Seguridad Ciudadana</b>
                        </div>
                        <strong>911 / (02241) 42-2022</strong>
                    </div>
                    <div class="contact-card blue">
                        <div>
                            <small>Defensa Civil / Centro de Monitoreo</small>
                            <b>Asistencia ante Emergencias Climáticas</b>
                        </div>
                        <strong>103 / (02241) 43-1341</strong>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="shell footer-inner">
            <span><strong>Sociedad de Bomberos Voluntarios de Chascomús</strong> &middot; Al servicio de la comunidad las 24 horas.</span>
            <span>
                <?php if ($user !== null): ?>
                    Sesion activa: <strong><?= e($user['full_name']) ?></strong> &middot; <a href="<?= e(panel_url_for($user['role'])) ?>">Acceder al Panel Interno</a>
                <?php else: ?>
                    <a href="login.php?acceso=bombero">Acceso Bomberos</a> &middot; <a href="login.php?acceso=admin">Administracion</a>
                <?php endif; ?>
            </span>
        </div>
    </footer>

    <div class="notice" id="notice" role="status"></div>

    <script>
        const modal = document.getElementById('alertasModal');
        const openBtnHero = document.getElementById('openAlertasModalBtn');
        const openBtnNav = document.getElementById('navAlertasBtn');
        const closeBtn = document.getElementById('closeAlertasModalBtn');

        function toggleModal(show) {
            if (show) {
                modal.classList.add('active');
            } else {
                modal.classList.remove('active');
            }
        }

        if (openBtnHero) openBtnHero.addEventListener('click', () => toggleModal(true));
        if (openBtnNav) openBtnNav.addEventListener('click', (e) => { e.preventDefault(); toggleModal(true); });
        if (closeBtn) closeBtn.addEventListener('click', () => toggleModal(false));

        modal.addEventListener('click', (e) => {
            if (e.target === modal) toggleModal(false);
        });

        // Mantener abierto el modal si se aplico un filtro por URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('openModal')) {
            toggleModal(true);
        }

        (function () {
            const feedCount = <?= count($alerts) ?>;
            const notice = document.getElementById('notice');

            function showNotice(text) {
                notice.textContent = text;
                notice.classList.add('show');
                window.setTimeout(() => notice.classList.remove('show'), 6000);
            }

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
                        showNotice('Nuevos reportes en el tablero oficial de Chascomús. Actualizando...');
                        window.setTimeout(() => window.location.reload(), 2500);
                    }
                } catch (err) {}
            }, 60000);
        })();
    </script>
</body>
</html>
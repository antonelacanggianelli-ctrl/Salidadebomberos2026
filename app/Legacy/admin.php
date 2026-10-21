<?php
declare(strict_types=1);

/**
 * Panel de administracion: supervisa las alertas publicadas, mantiene los
 * contactos de la portada y revisa la bitacora.
 *
 * La gestion del personal autorizado (listado, alta, edicion, suspension y
 * baja de cuentas) ya se migro a app/Controllers/Admin.php, que tambien es
 * quien carga este archivo para el resto de las secciones.
 */

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/repository.php';
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/forms.php';

send_security_headers();
$admin   = require_role('admin');
$adminId = (int) $admin['id'];

/* --------------------------------------------------------------- Acciones -- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $accion = post_str('accion', 30);
    $id     = (int) ($_POST['id'] ?? 0);

    switch ($accion) {

        /* ---- Alertas ---- */
        case 'alerta_guardar':
            [$data, $errors] = alert_validate();
            if ($errors !== []) {
                stash_old($data);
                flash('error', implode(' ', $errors));
                sdb_redirect('admin.php?vista=alerta_editar&id=' . $id);
            }
            if (alert_find($id) === null) {
                flash('error', 'La alerta indicada no existe.');
                sdb_redirect('admin.php?vista=alertas');
            }
            alert_update($id, $data);
            audit('alerta_editada_admin', '#' . $id . ' ' . $data['title'], $admin);
            flash('ok', 'Alerta #' . $id . ' actualizada.');
            sdb_redirect('admin.php?vista=alertas');

        case 'alerta_estado':
            $estado = post_str('estado', 20);
            if (alert_find($id) === null) {
                flash('error', 'La alerta indicada no existe.');
                sdb_redirect('admin.php?vista=alertas');
            }
            alert_set_status($id, $estado);
            audit('alerta_estado_admin', '#' . $id . ' -> ' . $estado, $admin);
            flash('ok', 'Alerta #' . $id . ' marcada como ' . $estado . '.');
            sdb_redirect('admin.php?vista=alertas');

        case 'alerta_eliminar':
            alert_delete($id);
            audit('alerta_eliminada_admin', '#' . $id, $admin);
            flash('ok', 'Alerta #' . $id . ' eliminada.');
            sdb_redirect('admin.php?vista=alertas');

        /* ---- Contactos de la portada ---- */
        case 'contacto_guardar':
            $label  = post_str('label', 60);
            $number = post_str('number', 60);
            $tone   = in_array(post_str('tone', 10), ['red', 'amber', 'blue'], true) ? post_str('tone', 10) : 'red';
            $pos    = (int) ($_POST['position'] ?? 0);

            if (mb_strlen($label) < 2 || mb_strlen($number) < 2) {
                flash('error', 'Completa la etiqueta y el numero o ubicacion del contacto.');
                sdb_redirect('admin.php?vista=contactos');
            }
            if ($id > 0) {
                contact_update($id, $label, $number, $tone, $pos);
                audit('contacto_editado', $label, $admin);
                flash('ok', 'Contacto actualizado.');
            } else {
                contact_create($label, $number, $tone, $pos);
                audit('contacto_creado', $label, $admin);
                flash('ok', 'Contacto agregado a la portada.');
            }
            sdb_redirect('admin.php?vista=contactos');

        case 'contacto_eliminar':
            contact_delete($id);
            audit('contacto_eliminado', '#' . $id, $admin);
            flash('ok', 'Contacto eliminado.');
            sdb_redirect('admin.php?vista=contactos');
    }

    sdb_redirect('admin.php');
}

/* ----------------------------------------------------------------- Vistas -- */

$vista = is_string($_GET['vista'] ?? null) ? $_GET['vista'] : 'resumen';
$tab   = match ($vista) {
    'alertas', 'alerta_editar'                    => 'Alertas',
    'contactos'                                   => 'Contactos',
    'bitacora'                                    => 'Bitacora',
    default                                       => 'Resumen',
};

// Los registros se buscan antes de dibujar nada: si no existen hay que poder
// redirigir, y una redireccion despues de imprimir la pagina no funciona.
$targetAlert = null;

if ($vista === 'alerta_editar') {
    $targetAlert = alert_find((int) ($_GET['id'] ?? 0));
    if ($targetAlert === null) {
        flash('error', 'La alerta indicada no existe.');
        sdb_redirect('admin.php?vista=alertas');
    }
}

panel_head('Administracion', $admin, panel_tabs('admin'), $tab);

switch ($vista) {

/* ============================================================= ALERTAS ==== */
case 'alertas':
    $filtros = [
        'status'   => is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '',
        'severity' => is_string($_GET['severidad'] ?? null) ? $_GET['severidad'] : '',
        'search'   => is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '',
    ];
    $lista = alerts_list($filtros);
    ?>
	<div class="panel-title">
		<div>
			<h1>Alertas del cuartel</h1>
			<p>Supervision completa: puedes corregir, despublicar o cerrar cualquier alerta cargada por el personal.</p>
		</div>
	</div>

	<div class="block">
		<header>
			<form class="filters" method="get" action="admin.php">
				<input type="hidden" name="vista" value="alertas">
				<div class="field">
					<label for="q">Buscar</label>
					<input id="q" name="q" type="search" value="<?= e($filtros['search']) ?>" placeholder="Titulo, lugar o texto">
				</div>
				<div class="field">
					<label for="estado">Estado</label>
					<select id="estado" name="estado">
						<option value="">Todos</option>
						<?php foreach (ALERT_STATUSES as $st): ?>
							<option value="<?= e($st) ?>" <?= $filtros['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="field">
					<label for="severidad">Severidad</label>
					<select id="severidad" name="severidad">
						<option value="">Todas</option>
						<?php foreach (ALERT_SEVERITIES as $sv): ?>
							<option value="<?= e($sv) ?>" <?= $filtros['severity'] === $sv ? 'selected' : '' ?>><?= e(ucfirst($sv)) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button class="btn btn-line" type="submit">Filtrar</button>
				<a class="btn btn-line" href="admin.php?vista=alertas">Limpiar</a>
			</form>
		</header>

		<?php if ($lista === []): ?>
			<div class="block-inner"><div class="empty-state"><strong>Sin resultados</strong>No hay alertas que coincidan con la busqueda.</div></div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="grid">
					<thead><tr><th>#</th><th>Incidente</th><th>Responsable</th><th>Severidad</th><th>Estado</th><th>Fechas</th><th>Acciones</th></tr></thead>
					<tbody>
						<?php foreach ($lista as $a): ?>
							<tr<?= (int) $a['published'] === 0 ? ' class="badge-off"' : '' ?>>
								<td class="num"><?= (int) $a['id'] ?></td>
								<td>
									<b><?= e($a['title']) ?></b>
									<div class="muted"><?= e($a['type']) ?> &middot; <?= e($a['location']) ?></div>
									<?php if ((int) $a['published'] === 0): ?><span class="tag neutral">No publicada</span><?php endif; ?>
								</td>
								<td><?= e($a['author_name']) ?><div class="muted"><?= e($a['author_user']) ?></div></td>
								<td><span class="tag <?= e(severity_class($a['severity'])) ?>"><?= e($a['severity']) ?></span></td>
								<td><span class="tag <?= e(status_class($a['status'])) ?>"><?= e($a['status']) ?></span></td>
								<td class="muted"><?= e(fmt_dt($a['created_at'])) ?><br><?= e(fmt_ago($a['updated_at'])) ?></td>
								<td>
									<div class="row-actions">
										<a class="btn btn-sm btn-line" href="admin.php?vista=alerta_editar&amp;id=<?= (int) $a['id'] ?>">Editar</a>
										<?php if ($a['status'] !== 'cerrada'): ?>
											<form class="inline-form" method="post" action="admin.php">
												<?= sdb_csrf_field() ?>
												<input type="hidden" name="accion" value="alerta_estado">
												<input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
												<input type="hidden" name="estado" value="cerrada">
												<button class="btn btn-sm btn-dark" type="submit">Cerrar</button>
											</form>
										<?php endif; ?>
										<form class="inline-form" method="post" action="admin.php"
										      onsubmit="return confirm('Eliminar definitivamente la alerta #<?= (int) $a['id'] ?>?');">
											<?= sdb_csrf_field() ?>
											<input type="hidden" name="accion" value="alerta_eliminar">
											<input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
											<button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
    <?php
    break;

case 'alerta_editar':
    $alert = $targetAlert;
    $old   = pull_old();
    if ($old !== null) {
        $alert = array_merge($alert, $old);
    }
    ?>
	<div class="panel-title">
		<div>
			<h1>Editar alerta #<?= (int) $alert['id'] ?></h1>
			<p>Cargada por <?= e($alert['author_name']) ?> (<?= e($alert['author_user']) ?>) el <?= e(fmt_dt($alert['created_at'])) ?>.</p>
		</div>
		<a class="btn btn-line btn-sm" href="admin.php?vista=alertas">&larr; Volver al listado</a>
	</div>
	<div class="block">
		<div class="block-inner">
			<?php render_alert_form($alert, 'admin.php', 'admin.php?vista=alertas', 'alerta_guardar'); ?>
		</div>
	</div>
    <?php
    break;

/* =========================================================== CONTACTOS ==== */
case 'contactos':
    $contactos = contacts_all();
    ?>
	<div class="panel-title">
		<div>
			<h1>Contactos de la portada</h1>
			<p>Telefonos de emergencia y punto de encuentro que ve la comunidad en la pagina publica.</p>
		</div>
	</div>

	<div class="block">
		<header><div><h2>Listado actual</h2><p>El orden define como se muestran en el sitio.</p></div></header>

		<?php // Los formularios viven fuera de la tabla; los campos los referencian con el atributo "form". ?>
		<?php foreach ($contactos as $c): ?>
			<form id="cto-edit-<?= (int) $c['id'] ?>" method="post" action="admin.php" hidden>
				<?= sdb_csrf_field() ?>
				<input type="hidden" name="accion" value="contacto_guardar">
				<input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
			</form>
			<form id="cto-del-<?= (int) $c['id'] ?>" method="post" action="admin.php" hidden
			      onsubmit="return confirm('Quitar este contacto de la portada?');">
				<?= sdb_csrf_field() ?>
				<input type="hidden" name="accion" value="contacto_eliminar">
				<input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
			</form>
		<?php endforeach; ?>

		<div class="table-wrap">
			<table class="grid">
				<thead><tr><th>Etiqueta</th><th>Numero o ubicacion</th><th>Color</th><th>Orden</th><th>Acciones</th></tr></thead>
				<tbody>
					<?php foreach ($contactos as $c): $fid = 'cto-edit-' . (int) $c['id']; ?>
						<tr>
							<td><input form="<?= $fid ?>" name="label" type="text" maxlength="60" required
							           value="<?= e($c['label']) ?>" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:4px;"></td>
							<td><input form="<?= $fid ?>" name="number" type="text" maxlength="60" required
							           value="<?= e($c['number']) ?>" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:4px;"></td>
							<td>
								<select form="<?= $fid ?>" name="tone" style="padding:8px;border:1px solid var(--line);border-radius:4px;">
									<?php foreach (['red' => 'Rojo (bomberos)', 'amber' => 'Ambar (emergencias)', 'blue' => 'Azul (ubicacion)'] as $val => $lbl): ?>
										<option value="<?= e($val) ?>" <?= $c['tone'] === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input form="<?= $fid ?>" name="position" type="number" min="0" max="99"
							           value="<?= (int) $c['position'] ?>" style="width:70px;padding:8px;border:1px solid var(--line);border-radius:4px;"></td>
							<td>
								<div class="row-actions">
									<button class="btn btn-sm btn-line" type="submit" form="<?= $fid ?>">Guardar</button>
									<button class="btn btn-sm btn-danger" type="submit" form="cto-del-<?= (int) $c['id'] ?>">Quitar</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ($contactos === []): ?>
						<tr><td colspan="5" class="muted">No hay contactos cargados. Agrega al menos uno para la portada.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="block">
		<header><div><h2>Agregar contacto</h2><p>Un telefono, una radio o el punto de encuentro designado.</p></div></header>
		<div class="block-inner">
			<form method="post" action="admin.php">
				<?= sdb_csrf_field() ?>
				<input type="hidden" name="accion" value="contacto_guardar">
				<input type="hidden" name="id" value="0">
				<div class="field-row">
					<div class="field">
						<label for="c_label">Etiqueta</label>
						<input id="c_label" name="label" type="text" maxlength="60" required placeholder="Ej.: Defensa Civil">
					</div>
					<div class="field">
						<label for="c_number">Numero o ubicacion</label>
						<input id="c_number" name="number" type="text" maxlength="60" required placeholder="Ej.: 103">
					</div>
				</div>
				<div class="field-row">
					<div class="field">
						<label for="c_tone">Color</label>
						<select id="c_tone" name="tone">
							<option value="red">Rojo (bomberos)</option>
							<option value="amber">Ambar (emergencias)</option>
							<option value="blue">Azul (ubicacion)</option>
						</select>
					</div>
					<div class="field">
						<label for="c_pos">Orden</label>
						<input id="c_pos" name="position" type="number" min="0" max="99" value="<?= count($contactos) + 1 ?>">
					</div>
				</div>
				<div class="form-actions"><button class="btn btn-primary" type="submit">Agregar contacto</button></div>
			</form>
		</div>
	</div>
    <?php
    break;

/* ============================================================ BITACORA ==== */
case 'bitacora':
    $registros = audit_recent(60);
    ?>
	<div class="panel-title">
		<div>
			<h1>Bitacora del sistema</h1>
			<p>Ingresos, altas de personal y cambios sobre las alertas, con fecha y direccion de red.</p>
		</div>
	</div>
	<div class="block">
		<div class="table-wrap">
			<table class="grid">
				<thead><tr><th>Fecha</th><th>Usuario</th><th>Accion</th><th>Detalle</th><th>Origen</th></tr></thead>
				<tbody>
					<?php foreach ($registros as $r): ?>
						<tr>
							<td class="muted"><?= e(fmt_dt($r['created_at'])) ?></td>
							<td><b><?= e($r['username']) ?></b></td>
							<td><span class="tag neutral"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td>
							<td class="muted"><?= e($r['detail']) ?></td>
							<td class="muted"><?= e($r['ip']) ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ($registros === []): ?>
						<tr><td colspan="5" class="muted">Todavia no hay movimientos registrados.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
    <?php
    break;

/* ============================================================= RESUMEN ==== */
default:
    $stats  = alerts_stats();
    $ustats = users_stats();
    $ultimas = alerts_list([], 8);
    $movimientos = audit_recent(8);
    ?>
	<div class="panel-title">
		<div>
			<h1>Resumen operativo</h1>
			<p>Hola <?= e($admin['full_name']) ?>. Estado general del cuartel y de la informacion publicada.</p>
		</div>
		<div class="row-actions">
			<a class="btn btn-primary btn-sm" href="admin.php?vista=usuario_nuevo">+ Autorizar bombero</a>
			<a class="btn btn-line btn-sm" href="admin.php?vista=alertas">Ver todas las alertas</a>
		</div>
	</div>

	<div class="cards">
		<div class="card accent-red"><small>Alertas activas</small><b><?= $stats['activas'] ?? 0 ?></b></div>
		<div class="card accent-amber"><small>Controladas</small><b><?= $stats['controladas'] ?? 0 ?></b></div>
		<div class="card accent-green"><small>Cerradas</small><b><?= $stats['cerradas'] ?? 0 ?></b></div>
		<div class="card"><small>Bomberos activos</small><b><?= $ustats['bomberos_activos'] ?? 0 ?></b></div>
		<div class="card"><small>Administradores</small><b><?= $ustats['admins'] ?? 0 ?></b></div>
	</div>

	<?php if ((int) $admin['must_change_password'] === 1): ?>
		<div class="credential-box">
			<b>Primer paso recomendado:</b> cambia la contrasena inicial del administrador desde
			<a href="perfil.php" style="font-weight:800;text-decoration:underline;">Mi perfil</a> y recien despues autoriza al personal.
		</div>
	<?php endif; ?>

	<div class="block">
		<header>
			<div><h2>Ultimas alertas</h2><p>Lo mas reciente cargado por el personal autorizado.</p></div>
			<a class="btn btn-sm btn-line" href="admin.php?vista=alertas">Ver todas</a>
		</header>
		<?php if ($ultimas === []): ?>
			<div class="block-inner"><div class="empty-state"><strong>Sin alertas cargadas</strong>Autoriza a un bombero para que comience a publicar informacion.</div></div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="grid">
					<thead><tr><th>#</th><th>Incidente</th><th>Responsable</th><th>Severidad</th><th>Estado</th><th>Actualizada</th></tr></thead>
					<tbody>
						<?php foreach ($ultimas as $a): ?>
							<tr>
								<td class="num"><?= (int) $a['id'] ?></td>
								<td><b><?= e($a['title']) ?></b><div class="muted"><?= e($a['location']) ?></div></td>
								<td class="muted"><?= e($a['author_name']) ?></td>
								<td><span class="tag <?= e(severity_class($a['severity'])) ?>"><?= e($a['severity']) ?></span></td>
								<td><span class="tag <?= e(status_class($a['status'])) ?>"><?= e($a['status']) ?></span></td>
								<td class="muted"><?= e(fmt_ago($a['updated_at'])) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="block">
		<header>
			<div><h2>Movimientos recientes</h2><p>Ultimas acciones registradas en la bitacora.</p></div>
			<a class="btn btn-sm btn-line" href="admin.php?vista=bitacora">Ver bitacora</a>
		</header>
		<div class="table-wrap">
			<table class="grid">
				<thead><tr><th>Fecha</th><th>Usuario</th><th>Accion</th><th>Detalle</th></tr></thead>
				<tbody>
					<?php foreach ($movimientos as $r): ?>
						<tr>
							<td class="muted"><?= e(fmt_dt($r['created_at'])) ?></td>
							<td><b><?= e($r['username']) ?></b></td>
							<td><span class="tag neutral"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td>
							<td class="muted"><?= e($r['detail']) ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ($movimientos === []): ?>
						<tr><td colspan="4" class="muted">Sin movimientos por ahora.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
    <?php
}

panel_foot();

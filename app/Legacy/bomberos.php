<?php
declare(strict_types=1);

/**
 * SUPERADO (Semana 3): este archivo ya no se usa. La ruta bomberos.php ahora
 * apunta a app/Controllers/Bomberos.php + app/Views/bomberos/*.php, un
 * Controller y Views reales de CodeIgniter 4. Se deja este archivo como
 * referencia de la logica original; no se ejecuta en ningun request.
 *
 * Panel del personal de bomberos: publica y mantiene las alertas del cuartel.
 * Cada bombero administra unicamente las alertas que el mismo creo.
 */

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/repository.php';
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/forms.php';

send_security_headers();
$user   = require_role('bombero');
$userId = (int) $user['id'];

/* --------------------------------------------------------------- Acciones -- */
/* own_alert_or_fail() se movio a app/Legacy/app/repository.php (Semana 3). */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $accion = post_str('accion', 20);
    $id     = (int) ($_POST['id'] ?? 0);

    if ($accion === 'guardar') {
        [$data, $errors] = alert_validate();

        if ($errors !== []) {
            stash_old($data);
            flash('error', implode(' ', $errors));
            sdb_redirect($id > 0 ? 'bomberos.php?vista=editar&id=' . $id : 'bomberos.php?vista=nueva');
        }

        if ($id > 0) {
            own_alert_or_fail($id, $userId);
            alert_update($id, $data);
            audit('alerta_editada', '#' . $id . ' ' . $data['title'], $user);
            flash('ok', 'Alerta #' . $id . ' actualizada.');
        } else {
            $id = alert_create($data, $userId);
            audit('alerta_creada', '#' . $id . ' ' . $data['title'], $user);
            flash('ok', 'Alerta #' . $id . ' publicada. Ya es visible en la portada.');
        }
        sdb_redirect('bomberos.php');
    }

    if ($accion === 'estado') {
        own_alert_or_fail($id, $userId);
        $estado = post_str('estado', 20);
        alert_set_status($id, $estado);
        audit('alerta_estado', '#' . $id . ' -> ' . $estado, $user);
        flash('ok', 'Alerta #' . $id . ' marcada como ' . $estado . '.');
        sdb_redirect('bomberos.php');
    }

    if ($accion === 'eliminar') {
        own_alert_or_fail($id, $userId);
        alert_delete($id);
        audit('alerta_eliminada', '#' . $id, $user);
        flash('ok', 'Alerta #' . $id . ' eliminada.');
        sdb_redirect('bomberos.php');
    }

    sdb_redirect('bomberos.php');
}

/* ----------------------------------------------------------------- Vistas -- */

$vista  = $_GET['vista'] ?? 'lista';
$misAlertas = alerts_list(['author' => $userId]);
$activas    = count(array_filter($misAlertas, static fn (array $a) => $a['status'] === 'activa'));
$abiertas   = count(array_filter($misAlertas, static fn (array $a) => $a['status'] !== 'cerrada'));

$tabActiva = match ($vista) {
    'nueva', 'editar' => 'Nueva alerta',
    default           => 'Mis alertas',
};

// La alerta a editar se resuelve antes de dibujar nada: si no le pertenece hay
// que redirigir, y una redireccion despues de imprimir la pagina no funciona.
$editAlert = $vista === 'editar'
    ? own_alert_or_fail((int) ($_GET['id'] ?? 0), $userId)
    : null;

panel_head('Panel de bomberos', $user, panel_tabs('bombero'), $tabActiva);
?>

<?php if ($vista === 'nueva' || $vista === 'editar'): ?>
	<?php
	$editing = $vista === 'editar';
	$alert   = $editing ? $editAlert : alert_blank();
	$old     = pull_old();
	if ($old !== null) {
		$alert = array_merge($alert, $old);
	}
	?>
	<div class="panel-title">
		<div>
			<h1><?= $editing ? 'Editar alerta #' . (int) $alert['id'] : 'Nueva alerta' ?></h1>
			<p><?= $editing
				? 'Actualiza la informacion a medida que cambia la situacion en el lugar.'
				: 'Publica un incidente. La comunidad lo vera en la portada al instante.' ?></p>
		</div>
		<a class="btn btn-line btn-sm" href="bomberos.php">&larr; Volver a mis alertas</a>
	</div>

	<div class="block">
		<div class="block-inner">
			<?php render_alert_form($alert, 'bomberos.php', 'bomberos.php'); ?>
		</div>
	</div>

<?php else: ?>
	<div class="panel-title">
		<div>
			<h1>Mis alertas</h1>
			<p>Hola <?= e($user['full_name']) ?>. Ultimo ingreso: <?= e(fmt_dt($user['last_login_at'])) ?>.</p>
		</div>
		<a class="btn btn-primary" href="bomberos.php?vista=nueva">+ Publicar alerta</a>
	</div>

	<div class="cards">
		<div class="card accent-red"><small>Activas</small><b><?= $activas ?></b></div>
		<div class="card accent-amber"><small>Sin cerrar</small><b><?= $abiertas ?></b></div>
		<div class="card"><small>Total publicadas</small><b><?= count($misAlertas) ?></b></div>
	</div>

	<div class="block">
		<header>
			<div>
				<h2>Alertas a mi cargo</h2>
				<p>Solo puedes editar o cerrar las alertas que cargaste con tu usuario.</p>
			</div>
		</header>

		<?php if ($misAlertas === []): ?>
			<div class="block-inner">
				<div class="empty-state">
					<strong>Todavia no cargaste alertas</strong>
					Cuando publiques un incidente aparecera aqui y en la portada del sitio.
				</div>
			</div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="grid">
					<thead>
						<tr><th>#</th><th>Incidente</th><th>Severidad</th><th>Estado</th><th>Publicada</th><th>Acciones</th></tr>
					</thead>
					<tbody>
						<?php foreach ($misAlertas as $a): ?>
							<tr<?= (int) $a['published'] === 0 ? ' class="badge-off"' : '' ?>>
								<td class="num"><?= (int) $a['id'] ?></td>
								<td>
									<b><?= e($a['title']) ?></b>
									<div class="muted"><?= e($a['type']) ?> &middot; <?= e($a['location']) ?></div>
									<?php if ((int) $a['published'] === 0): ?>
										<span class="tag neutral">No publicada</span>
									<?php endif; ?>
								</td>
								<td><span class="tag <?= e(severity_class($a['severity'])) ?>"><?= e($a['severity']) ?></span></td>
								<td><span class="tag <?= e(status_class($a['status'])) ?>"><?= e($a['status']) ?></span></td>
								<td class="muted"><?= e(fmt_dt($a['created_at'])) ?><br><?= e(fmt_ago($a['updated_at'])) ?></td>
								<td>
									<div class="row-actions">
										<a class="btn btn-sm btn-line" href="bomberos.php?vista=editar&amp;id=<?= (int) $a['id'] ?>">Editar</a>
										<?php if ($a['status'] === 'activa'): ?>
											<form class="inline-form" method="post" action="bomberos.php">
												<?= sdb_csrf_field() ?>
												<input type="hidden" name="accion" value="estado">
												<input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
												<input type="hidden" name="estado" value="controlada">
												<button class="btn btn-sm btn-line" type="submit">Controlar</button>
											</form>
										<?php endif; ?>
										<?php if ($a['status'] !== 'cerrada'): ?>
											<form class="inline-form" method="post" action="bomberos.php"
											      onsubmit="return confirm('Cerrar la alerta #<?= (int) $a['id'] ?>?');">
												<?= sdb_csrf_field() ?>
												<input type="hidden" name="accion" value="estado">
												<input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
												<input type="hidden" name="estado" value="cerrada">
												<button class="btn btn-sm btn-dark" type="submit">Cerrar</button>
											</form>
										<?php endif; ?>
										<form class="inline-form" method="post" action="bomberos.php"
										      onsubmit="return confirm('Eliminar definitivamente la alerta #<?= (int) $a['id'] ?>?');">
											<?= sdb_csrf_field() ?>
											<input type="hidden" name="accion" value="eliminar">
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
<?php endif; ?>

<?php panel_foot(); ?>

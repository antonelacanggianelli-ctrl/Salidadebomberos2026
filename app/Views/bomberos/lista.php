<?php
/**
 * Vista: listado "Mis alertas" del panel de bomberos.
 * Variables recibidas del Controller: $user, $misAlertas, $activas, $abiertas.
 *
 * @var array $user
 * @var array $misAlertas
 * @var int   $activas
 * @var int   $abiertas
 */
panel_head('Panel de bomberos', $user, panel_tabs('bombero'), 'Mis alertas');
?>

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
					<tr><th>#</th><th>Incidente</th><th>Zona</th><th>Severidad</th><th>Estado</th><th>Publicada</th><th>Acciones</th></tr>
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
							<td class="muted"><?= e($a['zone'] !== '' ? $a['zone'] : '—') ?></td>
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

<?php panel_foot(); ?>

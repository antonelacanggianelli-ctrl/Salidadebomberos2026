<?php
/**
 * Vista: listado del personal autorizado (bomberos y administradores).
 * Variables recibidas del Controller: $admin, $adminId, $bomberos, $admins.
 * Cada fila trae ademas 'alertas' (cantidad de alertas publicadas).
 *
 * @var array $admin
 * @var int   $adminId
 * @var array $bomberos
 * @var array $admins
 */
panel_head('Administracion', $admin, panel_tabs('admin'), 'Bomberos');
?>

<div class="panel-title">
	<div>
		<h1>Personal autorizado</h1>
		<p>Cada integrante ingresa con su propio usuario y contrasena, y responde por lo que publica.</p>
	</div>
	<a class="btn btn-primary" href="admin.php?vista=usuario_nuevo">+ Autorizar integrante</a>
</div>

<?php foreach ([['Bomberos autorizados', $bomberos, 'Publican y actualizan alertas.'], ['Administradores', $admins, 'Gestionan usuarios y supervisan el sistema.']] as [$titulo, $filas, $subtitulo]): ?>
	<div class="block">
		<header><div><h2><?= e($titulo) ?></h2><p><?= e($subtitulo) ?></p></div></header>
		<?php if ($filas === []): ?>
			<div class="block-inner"><div class="empty-state"><strong>Sin registros</strong>Aun no hay cuentas en esta categoria.</div></div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="grid">
					<thead><tr><th>Integrante</th><th>Usuario</th><th>Alertas</th><th>Ultimo ingreso</th><th>Estado</th><th>Acciones</th></tr></thead>
					<tbody>
						<?php foreach ($filas as $u): ?>
							<tr<?= (int) $u['active'] === 0 ? ' class="badge-off"' : '' ?>>
								<td>
									<b><?= e($u['full_name']) ?></b>
									<div class="muted"><?= $u['rank'] !== '' ? e($u['rank']) : 'Sin cargo asignado' ?><?= $u['phone'] !== '' ? ' &middot; ' . e($u['phone']) : '' ?></div>
								</td>
								<td><code><?= e($u['username']) ?></code><?= (int) $u['id'] === $adminId ? ' <span class="tag neutral">Tu</span>' : '' ?></td>
								<td class="num"><?= (int) $u['alertas'] ?></td>
								<td class="muted"><?= e(fmt_dt($u['last_login_at'])) ?></td>
								<td>
									<span class="tag <?= (int) $u['active'] === 1 ? 'st-controlada' : 'st-cerrada' ?>">
										<?= (int) $u['active'] === 1 ? 'activo' : 'suspendido' ?>
									</span>
									<?php if ((int) $u['must_change_password'] === 1): ?>
										<span class="tag sev-media">clave provisoria</span>
									<?php endif; ?>
								</td>
								<td>
									<div class="row-actions">
										<a class="btn btn-sm btn-line" href="admin.php?vista=usuario_editar&amp;id=<?= (int) $u['id'] ?>">Editar</a>
										<?php if ((int) $u['id'] !== $adminId): ?>
											<form class="inline-form" method="post" action="admin.php">
												<?= sdb_csrf_field() ?>
												<input type="hidden" name="accion" value="usuario_estado">
												<input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
												<input type="hidden" name="valor" value="<?= (int) $u['active'] === 1 ? '0' : '1' ?>">
												<button class="btn btn-sm btn-line" type="submit"><?= (int) $u['active'] === 1 ? 'Suspender' : 'Habilitar' ?></button>
											</form>
											<?php
											// El nombre lo puede elegir cada usuario desde su perfil: se pasa
											// como literal JSON (y luego se escapa para el atributo) para que
											// comillas o codigo en el nombre no se ejecuten como JavaScript.
											$confirmMsg = json_encode('Eliminar la cuenta de ' . $u['full_name'] . '?', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
											?>
											<form class="inline-form" method="post" action="admin.php"
											      onsubmit="return confirm(<?= e($confirmMsg) ?>);">
												<?= sdb_csrf_field() ?>
												<input type="hidden" name="accion" value="usuario_eliminar">
												<input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
												<button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
											</form>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
<?php endforeach; ?>

<?php panel_foot(); ?>

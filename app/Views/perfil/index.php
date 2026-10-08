<?php
/**
 * Vista: perfil propio (datos de contacto y cambio de contrasena).
 * Variables recibidas del Controller: $user, $misAlertas.
 *
 * @var array    $user
 * @var int|null $misAlertas
 */
panel_head('Mi perfil', $user, panel_tabs($user['role']), 'Mi perfil');
?>

<div class="panel-title">
	<div>
		<h1>Mi perfil</h1>
		<p>Cuenta <b><?= e($user['username']) ?></b> &middot; <?= $user['role'] === 'admin' ? 'Administrador' : 'Bombero autorizado' ?>
		   &middot; alta del <?= e(fmt_dt($user['created_at'])) ?>.</p>
	</div>
	<a class="btn btn-line btn-sm" href="<?= e(panel_url_for($user['role'])) ?>">&larr; Volver al panel</a>
</div>

<div class="cards">
	<div class="card"><small>Ultimo ingreso</small><b style="font-size:1.3rem;"><?= e(fmt_dt($user['last_login_at'])) ?></b></div>
	<?php if ($misAlertas !== null): ?>
		<div class="card accent-red"><small>Alertas publicadas</small><b><?= $misAlertas ?></b></div>
	<?php endif; ?>
	<div class="card <?= (int) $user['must_change_password'] === 1 ? 'accent-amber' : 'accent-green' ?>">
		<small>Estado de la clave</small>
		<b style="font-size:1.3rem;"><?= (int) $user['must_change_password'] === 1 ? 'Provisoria' : 'Definida por ti' ?></b>
	</div>
</div>

<div class="block">
	<header><div><h2>Datos de contacto</h2><p>Se muestran junto a las alertas que publicas.</p></div></header>
	<div class="block-inner">
		<form method="post" action="perfil.php">
			<?= sdb_csrf_field() ?>
			<input type="hidden" name="accion" value="datos">
			<div class="field-row">
				<div class="field">
					<label for="full_name">Nombre y apellido</label>
					<input id="full_name" name="full_name" type="text" maxlength="90" required value="<?= e($user['full_name']) ?>">
				</div>
				<div class="field">
					<label for="rank">Cargo o rango</label>
					<input id="rank" name="rank" type="text" maxlength="60" value="<?= e($user['rank']) ?>" placeholder="Ej.: Cabo">
				</div>
			</div>
			<div class="field">
				<label for="phone">Telefono</label>
				<input id="phone" name="phone" type="text" maxlength="40" value="<?= e($user['phone']) ?>" placeholder="Opcional">
			</div>
			<div class="form-actions"><button class="btn btn-line" type="submit">Guardar datos</button></div>
		</form>
	</div>
</div>

<div class="block">
	<header><div><h2>Cambiar contrasena</h2><p>Minimo <?= MIN_PASSWORD_LENGTH ?> caracteres, combinando letras y numeros.</p></div></header>
	<div class="block-inner">
		<?php if ((int) $user['must_change_password'] === 1): ?>
			<div class="msg warn"><div>Tu contrasena fue asignada por la administracion. Define una propia antes de seguir operando.</div></div>
		<?php endif; ?>
		<form method="post" action="perfil.php" autocomplete="off">
			<?= sdb_csrf_field() ?>
			<input type="hidden" name="accion" value="clave">
			<div class="field">
				<label for="actual">Contrasena actual</label>
				<input id="actual" name="actual" type="password" required autocomplete="current-password" maxlength="200">
			</div>
			<div class="field-row">
				<div class="field">
					<label for="nueva">Contrasena nueva</label>
					<input id="nueva" name="nueva" type="password" required autocomplete="new-password" maxlength="200">
				</div>
				<div class="field">
					<label for="repetir">Repetir contrasena nueva</label>
					<input id="repetir" name="repetir" type="password" required autocomplete="new-password" maxlength="200">
				</div>
			</div>
			<div class="form-actions"><button class="btn btn-primary" type="submit">Actualizar contrasena</button></div>
		</form>
	</div>
</div>

<?php panel_foot(); ?>

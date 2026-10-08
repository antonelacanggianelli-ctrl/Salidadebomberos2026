<?php
/**
 * Vista: alta o edicion de un integrante del personal autorizado.
 * Variables recibidas del Controller: $admin, $editing, $target.
 *
 * @var array $admin
 * @var bool  $editing
 * @var array $target
 */
panel_head('Administracion', $admin, panel_tabs('admin'), 'Bomberos');
?>

<div class="panel-title">
	<div>
		<h1><?= $editing ? 'Editar integrante' : 'Autorizar integrante' ?></h1>
		<p><?= $editing
			? 'Actualiza los datos de la cuenta. Deja la contrasena vacia para conservarla.'
			: 'Crea la cuenta con la que esta persona podra publicar alertas.' ?></p>
	</div>
	<a class="btn btn-line btn-sm" href="admin.php?vista=usuarios">&larr; Volver al listado</a>
</div>

<div class="block">
	<div class="block-inner">
		<form method="post" action="admin.php">
			<?= sdb_csrf_field() ?>
			<input type="hidden" name="accion" value="usuario_guardar">
			<input type="hidden" name="id" value="<?= (int) $target['id'] ?>">

			<div class="field-row">
				<div class="field">
					<label for="full_name">Nombre y apellido</label>
					<input id="full_name" name="full_name" type="text" maxlength="90" required
					       value="<?= e($target['full_name']) ?>" placeholder="Ej.: Maria Gomez">
				</div>
				<div class="field">
					<label for="username">Usuario de acceso</label>
					<input id="username" name="username" type="text" maxlength="30" required
					       value="<?= e($target['username']) ?>" placeholder="maria.gomez" autocomplete="off">
					<span class="hint">Entre 4 y 30 caracteres: letras, numeros, punto, guion o guion bajo.</span>
				</div>
			</div>

			<div class="field-row">
				<div class="field">
					<label for="rank">Cargo o rango</label>
					<input id="rank" name="rank" type="text" maxlength="60"
					       value="<?= e($target['rank']) ?>" placeholder="Ej.: Sargento primero">
				</div>
				<div class="field">
					<label for="phone">Telefono de contacto</label>
					<input id="phone" name="phone" type="text" maxlength="40"
					       value="<?= e($target['phone']) ?>" placeholder="Opcional">
				</div>
			</div>

			<div class="field">
				<label for="role">Rol en el sistema</label>
				<select id="role" name="role" <?= $editing ? 'disabled' : '' ?>>
					<option value="bombero" <?= $target['role'] === 'bombero' ? 'selected' : '' ?>>Bombero &mdash; publica y actualiza alertas</option>
					<option value="admin" <?= $target['role'] === 'admin' ? 'selected' : '' ?>>Administrador &mdash; gestiona usuarios y supervisa todo</option>
				</select>
				<span class="hint"><?= $editing
					? 'El rol no se modifica una vez creada la cuenta; si hace falta, crea una cuenta nueva.'
					: 'Autoriza como bombero al personal que carga la informacion de las alertas.' ?></span>
			</div>

			<div class="field-row">
				<div class="field">
					<label for="password"><?= $editing ? 'Nueva contrasena (opcional)' : 'Contrasena inicial' ?></label>
					<input id="password" name="password" type="password" maxlength="200" autocomplete="new-password"
					       <?= $editing ? '' : 'required' ?> placeholder="Minimo <?= MIN_PASSWORD_LENGTH ?> caracteres">
				</div>
				<div class="field">
					<label for="password2">Repetir contrasena</label>
					<input id="password2" name="password2" type="password" maxlength="200" autocomplete="new-password"
					       <?= $editing ? '' : 'required' ?>>
				</div>
			</div>
			<div class="form-actions" style="margin:-6px 0 16px;">
				<button class="btn btn-sm btn-line" type="button" id="genPass">Generar contrasena segura</button>
			</div>

			<label class="check">
				<input type="checkbox" name="must_change" value="1" <?= (int) ($target['must_change_password'] ?? 1) === 1 ? 'checked' : '' ?>>
				<span>Pedir que cambie la contrasena en su primer ingreso (recomendado).</span>
			</label>

			<label class="check">
				<input type="checkbox" name="active" value="1" <?= (int) $target['active'] === 1 ? 'checked' : '' ?>>
				<span>Cuenta habilitada para ingresar al sistema.</span>
			</label>

			<div class="form-actions">
				<button class="btn btn-primary" type="submit"><?= $editing ? 'Guardar cambios' : 'Autorizar y crear cuenta' ?></button>
				<a class="btn btn-line" href="admin.php?vista=usuarios">Cancelar</a>
			</div>
		</form>
	</div>
</div>

<script>
	document.getElementById('genPass').addEventListener('click', function () {
		const abc = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		const bytes = new Uint32Array(12);
		crypto.getRandomValues(bytes);
		let out = '';
		bytes.forEach(n => { out += abc[n % abc.length]; });
		out = out.slice(0, 10) + (bytes[0] % 10) + 'a';
		document.getElementById('password').value = out;
		document.getElementById('password2').value = out;
	});
</script>

<?php panel_foot(); ?>

<?php
declare(strict_types=1);

/**
 * Panel de administracion: autoriza al personal de bomberos, supervisa las
 * alertas publicadas, mantiene los contactos de la portada y revisa la bitacora.
 */

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/repository.php';
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/forms.php';

send_security_headers();
$admin   = require_role('admin');
$adminId = (int) $admin['id'];

/** Impide dejar el sistema sin ningun administrador activo. */
function last_active_admin(int $id): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?");
    $stmt->execute([$id]);

    return (int) $stmt->fetchColumn() === 0;
}

/** Datos del formulario de usuario, ya validados. */
function user_form_data(?int $editingId): array
{
    $errors = [];
    // El rol queda fijo después de crear la cuenta. El selector se deshabilita
    // en la vista de edición y, por eso, no viaja en el POST: debe recuperarse
    // desde la base para no convertir accidentalmente un admin en bombero.
    $existingRole = 'bombero';
    if ($editingId !== null) {
        $existing = user_find($editingId);
        $existingRole = ($existing['role'] ?? '') === 'admin' ? 'admin' : 'bombero';
    }
    $data = [
        'username'  => mb_strtolower(post_str('username', 30)),
        'full_name' => post_str('full_name', 90),
        'role'      => $editingId === null
            ? (post_str('role', 10) === 'admin' ? 'admin' : 'bombero')
            : $existingRole,
        'rank'      => post_str('rank', 60),
        'phone'     => post_str('phone', 40),
        'active'    => isset($_POST['active']) ? 1 : 0,
    ];

    if (($problem = username_problem($data['username'])) !== null) {
        $errors[] = $problem;
    } elseif (username_taken($data['username'], $editingId)) {
        $errors[] = 'Ese nombre de usuario ya esta en uso.';
    }
    if (mb_strlen($data['full_name']) < 3) {
        $errors[] = 'Escribe el nombre y apellido del integrante.';
    }

    return [$data, $errors];
}

/* --------------------------------------------------------------- Acciones -- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $accion = post_str('accion', 30);
    $id     = (int) ($_POST['id'] ?? 0);

    switch ($accion) {

        /* ---- Personal autorizado ---- */
        case 'usuario_guardar':
            $editing = $id > 0;
            [$data, $errors] = user_form_data($editing ? $id : null);
            $password = (string) ($_POST['password'] ?? '');
            $confirm  = (string) ($_POST['password2'] ?? '');
            $mustChange = isset($_POST['must_change']);

            if (!$editing || $password !== '' || $confirm !== '') {
                if (($problem = password_problem($password, $confirm)) !== null) {
                    $errors[] = $problem;
                }
            }

            if ($errors !== []) {
                stash_old($data);
                flash('error', implode(' ', $errors));
                sdb_redirect($editing
                    ? 'admin.php?vista=usuario_editar&id=' . $id
                    : 'admin.php?vista=usuario_nuevo');
            }

            if ($editing) {
                $target = user_find($id);
                if ($target === null) {
                    flash('error', 'El usuario indicado no existe.');
                    sdb_redirect('admin.php?vista=usuarios');
                }
                if ($id === $adminId && $data['active'] === 0) {
                    flash('error', 'No puedes desactivar tu propia cuenta.');
                    sdb_redirect('admin.php?vista=usuario_editar&id=' . $id);
                }
                if ($target['role'] === 'admin' && $data['active'] === 0 && last_active_admin($id)) {
                    flash('error', 'Debe quedar al menos un administrador activo.');
                    sdb_redirect('admin.php?vista=usuario_editar&id=' . $id);
                }

                user_update($id, $data);
                if ($password !== '') {
                    user_set_password($id, $password, $mustChange);
                }
                audit('usuario_editado', $data['username'], $admin);
                flash('ok', 'Datos de ' . $data['full_name'] . ' actualizados.');
            } else {
                user_create($data, $password, $adminId, $mustChange);
                audit('usuario_creado', $data['username'] . ' (' . $data['role'] . ')', $admin);
                flash('ok', 'Se autorizo a ' . $data['full_name'] . '. Entregale el usuario "' . $data['username'] . '" y su contrasena inicial.');
            }
            sdb_redirect('admin.php?vista=usuarios');

        case 'usuario_estado':
            $target = user_find($id);
            if ($target === null) {
                flash('error', 'El usuario indicado no existe.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            $activar = post_str('valor', 5) === '1';
            if (!$activar && $id === $adminId) {
                flash('error', 'No puedes desactivar tu propia cuenta.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            if (!$activar && $target['role'] === 'admin' && last_active_admin($id)) {
                flash('error', 'Debe quedar al menos un administrador activo.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            user_set_active($id, $activar);
            audit($activar ? 'usuario_activado' : 'usuario_desactivado', $target['username'], $admin);
            flash('ok', 'Cuenta de ' . $target['full_name'] . ($activar ? ' habilitada.' : ' suspendida.'));
            sdb_redirect('admin.php?vista=usuarios');

        case 'usuario_eliminar':
            $target = user_find($id);
            if ($target === null) {
                flash('error', 'El usuario indicado no existe.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            if ($id === $adminId) {
                flash('error', 'No puedes eliminar tu propia cuenta.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            if ($target['role'] === 'admin' && last_active_admin($id)) {
                flash('error', 'Debe quedar al menos un administrador activo.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            if (user_alert_count($id) > 0) {
                user_set_active($id, false);
                audit('usuario_desactivado', $target['username'] . ' (tiene alertas asociadas)', $admin);
                flash('warn', $target['full_name'] . ' tiene alertas publicadas, por eso se suspendio la cuenta en lugar de borrarla. Asi se conserva el historial.');
                sdb_redirect('admin.php?vista=usuarios');
            }
            user_delete($id);
            audit('usuario_eliminado', $target['username'], $admin);
            flash('ok', 'Se elimino la cuenta de ' . $target['full_name'] . '.');
            sdb_redirect('admin.php?vista=usuarios');

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

        /* ---- Suscriptores de SMS ---- */
        case 'suscriptor_estado':
            $activar = post_str('valor', 5) === '1';
            subscriber_set_active($id, $activar);
            audit($activar ? 'suscriptor_activado' : 'suscriptor_desactivado', '#' . $id, $admin);
            flash('ok', $activar ? 'Numero reactivado.' : 'Numero dado de baja.');
            sdb_redirect('admin.php?vista=suscriptores');

        case 'suscriptor_eliminar':
            subscriber_delete($id);
            audit('suscriptor_eliminado', '#' . $id, $admin);
            flash('ok', 'Numero eliminado de la lista.');
            sdb_redirect('admin.php?vista=suscriptores');
    }

    sdb_redirect('admin.php');
}

/* ----------------------------------------------------------------- Vistas -- */

$vista = is_string($_GET['vista'] ?? null) ? $_GET['vista'] : 'resumen';
$tab   = match ($vista) {
    'usuarios', 'usuario_nuevo', 'usuario_editar' => 'Bomberos',
    'alertas', 'alerta_editar'                    => 'Alertas',
    'contactos'                                   => 'Contactos',
    'suscriptores'                                => 'Suscriptores',
    'bitacora'                                    => 'Bitacora',
    default                                       => 'Resumen',
};

// Los registros se buscan antes de dibujar nada: si no existen hay que poder
// redirigir, y una redireccion despues de imprimir la pagina no funciona.
$targetUser  = null;
$targetAlert = null;

if ($vista === 'usuario_editar') {
    $targetUser = user_find((int) ($_GET['id'] ?? 0));
    if ($targetUser === null) {
        flash('error', 'El usuario indicado no existe.');
        sdb_redirect('admin.php?vista=usuarios');
    }
}
if ($vista === 'alerta_editar') {
    $targetAlert = alert_find((int) ($_GET['id'] ?? 0));
    if ($targetAlert === null) {
        flash('error', 'La alerta indicada no existe.');
        sdb_redirect('admin.php?vista=alertas');
    }
}

panel_head('Administracion', $admin, panel_tabs('admin'), $tab);

switch ($vista) {

/* ============================================================ USUARIOS ==== */
case 'usuarios':
    $bomberos = users_list('bombero');
    $admins   = users_list('admin');
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
									<td class="num"><?= user_alert_count((int) $u['id']) ?></td>
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
												<form class="inline-form" method="post" action="admin.php"
												      onsubmit="return confirm('Eliminar la cuenta de <?= e($u['full_name']) ?>?');">
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
    <?php
    break;

/* ================================================ ALTA / EDICION USUARIO == */
case 'usuario_nuevo':
case 'usuario_editar':
    $editing = $vista === 'usuario_editar';
    $target  = [
        'id' => 0, 'username' => '', 'full_name' => '', 'role' => 'bombero',
        'rank' => '', 'phone' => '', 'active' => 1, 'must_change_password' => 1,
    ];
    if ($editing) {
        $target = $targetUser;
    }
    $old = pull_old();
    if ($old !== null) {
        $target = array_merge($target, $old);
    }
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
    <?php
    break;

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

/* ========================================================= SUSCRIPTORES ==== */
case 'suscriptores':
    $subs = subscribers_all();
    ?>
	<div class="panel-title">
		<div>
			<h1>Suscriptores de SMS</h1>
			<p>Vecinos que pidieron recibir un aviso por mensaje cuando se publica una alerta. El envio no es automatico: usa esta lista para escribirles por SMS o WhatsApp cuando corresponda.</p>
		</div>
		<button class="btn btn-line btn-sm" type="button" id="copiarNumeros">Copiar todos los numeros</button>
	</div>

	<div class="cards">
		<div class="card accent-green"><small>Activos</small><b><?= subscribers_count_active() ?></b></div>
		<div class="card"><small>Total anotados</small><b><?= count($subs) ?></b></div>
	</div>

	<div class="block">
		<?php if ($subs === []): ?>
			<div class="block-inner"><div class="empty-state"><strong>Sin anotados todavia</strong>Cuando alguien se suscriba desde la portada, va a aparecer aca.</div></div>
		<?php else: ?>
			<div class="table-wrap">
				<table class="grid">
					<thead><tr><th>Nombre</th><th>Celular</th><th>Anotado</th><th>Estado</th><th>Acciones</th></tr></thead>
					<tbody>
						<?php foreach ($subs as $s): ?>
							<tr<?= (int) $s['active'] === 0 ? ' class="badge-off"' : '' ?>>
								<td><?= $s['name'] !== '' ? e($s['name']) : '<span class="muted">Sin nombre</span>' ?></td>
								<td><code><?= e($s['phone']) ?></code></td>
								<td class="muted"><?= e(fmt_dt($s['created_at'])) ?></td>
								<td><span class="tag <?= (int) $s['active'] === 1 ? 'st-controlada' : 'st-cerrada' ?>"><?= (int) $s['active'] === 1 ? 'activo' : 'de baja' ?></span></td>
								<td>
									<div class="row-actions">
										<form class="inline-form" method="post" action="admin.php">
											<?= sdb_csrf_field() ?>
											<input type="hidden" name="accion" value="suscriptor_estado">
											<input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
											<input type="hidden" name="valor" value="<?= (int) $s['active'] === 1 ? '0' : '1' ?>">
											<button class="btn btn-sm btn-line" type="submit"><?= (int) $s['active'] === 1 ? 'Dar de baja' : 'Reactivar' ?></button>
										</form>
										<form class="inline-form" method="post" action="admin.php"
										      onsubmit="return confirm('Eliminar este numero de la lista?');">
											<?= sdb_csrf_field() ?>
											<input type="hidden" name="accion" value="suscriptor_eliminar">
											<input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
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

	<script>
		document.getElementById('copiarNumeros')?.addEventListener('click', function () {
			const numeros = <?= json_encode(array_column(array_filter($subs, static fn ($s) => (int) $s['active'] === 1), 'phone'), JSON_UNESCAPED_SLASHES) ?>;
			if (numeros.length === 0) { return; }
			navigator.clipboard.writeText(numeros.join(', ')).then(() => {
				const btn = document.getElementById('copiarNumeros');
				const original = btn.textContent;
				btn.textContent = 'Copiado!';
				setTimeout(() => { btn.textContent = original; }, 2000);
			});
		});
	</script>
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
		<div class="card"><small>Suscriptores SMS</small><b><?= subscribers_count_active() ?></b></div>
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

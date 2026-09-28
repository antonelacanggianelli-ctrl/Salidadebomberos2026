<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

/** Valores por defecto de una alerta nueva. */
function alert_blank(): array
{
    return [
        'id'            => 0,
        'title'         => '',
        'type'          => ALERT_TYPES[0],
        'severity'      => 'media',
        'status'        => 'activa',
        'zone'          => ALERT_ZONES[0],
        'location'      => '',
        'meeting_point' => '',
        'description'   => '',
        'instructions'  => '',
        'published'     => 1,
    ];
}

/**
 * Formulario compartido de alta y edicion de alertas.
 *
 * @param array  $alert       datos actuales (usa alert_blank() para una nueva)
 * @param string $action      URL destino del formulario
 * @param string $cancelUrl   URL del boton "cancelar"
 * @param string $actionName  valor del campo "accion" que espera el panel
 */
function render_alert_form(array $alert, string $action, string $cancelUrl, string $actionName = 'guardar'): void
{
    $isNew = (int) ($alert['id'] ?? 0) === 0;
    ?>
	<form method="post" action="<?= e($action) ?>">
		<?= sdb_csrf_field() ?>
		<input type="hidden" name="accion" value="<?= e($actionName) ?>">
		<input type="hidden" name="id" value="<?= (int) ($alert['id'] ?? 0) ?>">

		<div class="field">
			<label for="title">Titulo del incidente</label>
			<input id="title" name="title" type="text" maxlength="120" required
			       value="<?= e($alert['title']) ?>" placeholder="Ej.: Incendio en deposito del ala norte">
		</div>

		<div class="field-row">
			<div class="field">
				<label for="type">Tipo de emergencia</label>
				<select id="type" name="type" required>
					<?php foreach (ALERT_TYPES as $type): ?>
						<option value="<?= e($type) ?>" <?= $alert['type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="field">
				<label for="severity">Severidad</label>
				<select id="severity" name="severity" required>
					<?php foreach (ALERT_SEVERITIES as $sev): ?>
						<option value="<?= e($sev) ?>" <?= $alert['severity'] === $sev ? 'selected' : '' ?>><?= e(ucfirst($sev)) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="field-row">
			<div class="field">
				<label for="zone">Zona</label>
				<select id="zone" name="zone" required>
					<?php foreach (ALERT_ZONES as $zone): ?>
						<option value="<?= e($zone) ?>" <?= ($alert['zone'] ?? '') === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
					<?php endforeach; ?>
				</select>
				<span class="hint">Se usa para filtrar el portal publico y para las notificaciones push por zona.</span>
			</div>
			<div class="field">
				<label for="location">Ubicacion</label>
				<input id="location" name="location" type="text" maxlength="160" required
				       value="<?= e($alert['location']) ?>" placeholder="Calle, edificio, piso o sector">
			</div>
		</div>

		<div class="field">
			<label for="description">Descripcion de la situacion</label>
			<textarea id="description" name="description" maxlength="2000" required
			          placeholder="Que ocurrio, alcance, personas afectadas, recursos desplegados..."><?= e($alert['description']) ?></textarea>
		</div>

		<?php // El punto de encuentro y las indicaciones se sacaron del formulario para simplificarlo;
		// si una alerta vieja ya tenia algo cargado ahi, se conserva sin mostrarse. ?>
		<input type="hidden" name="meeting_point" value="<?= e($alert['meeting_point']) ?>">
		<input type="hidden" name="instructions" value="<?= e($alert['instructions']) ?>">

		<div class="field">
			<label for="status">Estado</label>
			<select id="status" name="status" required>
				<?php foreach (ALERT_STATUSES as $st): ?>
					<option value="<?= e($st) ?>" <?= $alert['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
				<?php endforeach; ?>
			</select>
			<span class="hint">Las alertas cerradas dejan de mostrarse como vigentes y pasan al historial.</span>
		</div>

		<label class="check">
			<input type="checkbox" name="published" value="1" <?= (int) $alert['published'] === 1 ? 'checked' : '' ?>>
			<span>Publicar en el sitio publico. Si la desmarcas, la alerta queda visible solo para el personal del cuartel.</span>
		</label>

		<div class="form-actions">
			<button class="btn btn-primary" type="submit"><?= $isNew ? 'Publicar alerta' : 'Guardar cambios' ?></button>
			<a class="btn btn-line" href="<?= e($cancelUrl) ?>">Cancelar</a>
		</div>
	</form>
    <?php
}

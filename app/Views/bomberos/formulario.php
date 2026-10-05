<?php
/**
 * Vista: formulario de alta/edicion de alertas del panel de bomberos.
 * Variables recibidas del Controller: $user, $vista, $editAlert.
 *
 * @var array      $user
 * @var string     $vista
 * @var array|null $editAlert
 */
panel_head('Panel de bomberos', $user, panel_tabs('bombero'), 'Nueva alerta');

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

<?php panel_foot(); ?>

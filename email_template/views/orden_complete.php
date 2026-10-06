<?php
/**
 * Pedido confirmado. Variables:
 *  $empresa, $nombre, $numOrden, $fecha, $retiro (texto o ''), $detalle (items), $dinero (totales formateados),
 *  $pagos, $transacciones (líneas de texto), $entrega (texto)
 */
$fuente = "Montserrat, Helvetica, Roboto, Arial, sans-serif";
$color = emailEsc($empresa['color']);
$td = "font-family:$fuente;font-size:14px;color:#4A4A4A;";
?>
<h1 style="margin:0 0 20px 0;font-family:<?= $fuente ?>;font-size:32px;line-height:38px;font-weight:bold;color:#4A4A4A;">Pedido confirmado</h1>

<h3 style="margin:0 0 10px 0;font-family:<?= $fuente ?>;font-size:17px;line-height:22px;color:#4A4A4A;">Hola <?= emailEsc($nombre) ?>!</h3>
<p style="margin:0 0 20px 0;font-family:<?= $fuente ?>;font-size:17px;line-height:26px;color:#4A4A4A;">
    Tu pedido <strong>#<?= emailEsc($numOrden) ?></strong> fue receptado el <?= emailEsc($fecha) ?>.
    <?php if ($retiro !== ''): ?>
        <br><span style="color:<?= $color ?>;"><?= emailEsc($retiro) ?></span>
    <?php endif; ?>
    <br>Este es el detalle del pedido:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFFFFF;border-radius:13px;">
    <tr>
        <td style="padding:20px;">

            <!-- PRODUCTOS -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <?php foreach ($detalle as $item): ?>
                    <tr>
                        <td valign="top" width="40" style="<?= $td ?>padding:10px 0;"><strong><?= emailEsc($item['cantidad']) ?> x</strong></td>
                        <?php if ($item['imagen'] !== ''): ?>
                            <td valign="top" width="90" style="padding:10px 10px 10px 0;">
                                <img src="<?= emailEsc($item['imagen']) ?>" alt="" width="80" style="display:block;border:0;max-width:80px;height:auto;">
                            </td>
                        <?php endif; ?>
                        <td valign="top" style="<?= $td ?>padding:10px 0;">
                            <?= emailEsc($item['nombre']) ?>
                            <?php foreach ($item['opciones'] as $opcion): ?>
                                <div style="font-size:11px;line-height:16px;color:#777777;"><?= emailEsc($opcion) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td valign="top" align="right" style="<?= $td ?>padding:10px 0;white-space:nowrap;">$<?= emailEsc($item['precio']) ?></td>
                    </tr>
                    <tr><td colspan="4" style="border-bottom:1px solid #E0E0E0;font-size:0;line-height:0;height:1px;">&nbsp;</td></tr>
                <?php endforeach; ?>
            </table>

            <!-- TOTALES -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;">
                <?php foreach ($dinero as $linea): ?>
                    <tr>
                        <td style="<?= $td ?>padding:8px 0;<?= $linea['fuerte'] ? 'font-weight:bold;' : '' ?>"><?= emailEsc($linea['titulo']) ?></td>
                        <td align="right" style="<?= $td ?>padding:8px 0;<?= $linea['fuerte'] ? 'font-weight:bold;' : '' ?>">$<?= emailEsc($linea['valor']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <hr style="border:0;border-top:1px solid #929292;margin:20px 0;">

            <!-- PAGO Y ENTREGA -->
            <p style="margin:0 0 8px 0;<?= $td ?>"><strong>FORMA DE PAGO:</strong></p>
            <?php foreach ($pagos as $pago): ?>
                <p style="margin:0 0 4px 0;font-family:<?= $fuente ?>;font-size:12px;color:#4A4A4A;"><?= emailEsc($pago) ?></p>
            <?php endforeach; ?>
            <?php foreach ($transacciones as $linea): ?>
                <p style="margin:0 0 4px 0;font-family:<?= $fuente ?>;font-size:12px;color:#4A4A4A;"><?= emailEsc($linea) ?></p>
            <?php endforeach; ?>
            <p style="margin:12px 0 0 0;font-family:<?= $fuente ?>;font-size:12px;color:#4A4A4A;"><?= emailEsc($entrega) ?></p>

        </td>
    </tr>
</table>

<?php
/**
 * Cupones comprados. Variables: $empresa, $nombre, $numOrden, $fecha, $cupones (lista de códigos)
 */
$fuente = "Montserrat, Helvetica, Roboto, Arial, sans-serif";
?>
<h1 style="margin:0 0 20px 0;font-family:<?= $fuente ?>;font-size:32px;line-height:38px;font-weight:bold;color:#4A4A4A;">Tus cupones</h1>

<h3 style="margin:0 0 10px 0;font-family:<?= $fuente ?>;font-size:17px;line-height:22px;color:#4A4A4A;">Hola <?= emailEsc($nombre) ?>!</h3>
<p style="margin:0 0 20px 0;font-family:<?= $fuente ?>;font-size:17px;line-height:26px;color:#4A4A4A;">
    Tu pedido <strong>#<?= emailEsc($numOrden) ?></strong> fue receptado el <?= emailEsc($fecha) ?>.
    <br>Estos son los códigos que adquiriste:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFFFFF;border-radius:13px;">
    <tr>
        <td style="padding:20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <?php foreach ($cupones as $codigo): ?>
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #E0E0E0;font-family:<?= $fuente ?>;font-size:18px;font-weight:bold;letter-spacing:1px;color:#4A4A4A;"><?= emailEsc($codigo) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
</table>

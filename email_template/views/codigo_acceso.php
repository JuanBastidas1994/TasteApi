<?php
/**
 * Código de inicio de sesión / registro. Variables: $empresa, $titulo, $saludo, $instruccion, $codigo
 */
$fuente = "Montserrat, Helvetica, Roboto, Arial, sans-serif";
?>
<h1 style="margin:0 0 20px 0;font-family:<?= $fuente ?>;font-size:32px;line-height:38px;font-weight:bold;color:#4A4A4A;"><?= emailEsc($titulo) ?></h1>

<h3 style="margin:0 0 10px 0;font-family:<?= $fuente ?>;font-size:17px;line-height:22px;color:#4A4A4A;"><?= emailEsc($saludo) ?></h3>
<p style="margin:0 0 20px 0;font-family:<?= $fuente ?>;font-size:17px;line-height:26px;color:#4A4A4A;"><?= emailEsc($instruccion) ?></p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFFFFF;border-radius:13px;">
    <tr>
        <td align="center" style="padding:30px 20px;font-family:<?= $fuente ?>;font-size:40px;font-weight:bold;letter-spacing:8px;color:<?= emailEsc($empresa['color']) ?>;"><?= emailEsc($codigo) ?></td>
    </tr>
</table>

<p style="margin:20px 0 0 0;font-family:<?= $fuente ?>;font-size:13px;line-height:20px;color:#777777;">Si no fuiste tú quien lo solicitó, puedes ignorar este correo.</p>

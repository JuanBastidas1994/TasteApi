<?php
/**
 * Marco común de todos los correos. Variables:
 *  $empresa   ['nombre','web','logo','color','correo']
 *  $redes     [['nombre' => 'Facebook', 'link' => '...'], ...]
 *  $titulo    <title> del correo
 *  $contenido HTML ya armado de la vista (se inserta tal cual)
 */
$fuente = "Montserrat, Helvetica, Roboto, Arial, sans-serif";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title><?= emailEsc($titulo) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#F7F7F7;font-family:<?= $fuente ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F7F7F7;">
    <tr>
        <td align="center" style="padding:0;">

            <!-- CABECERA -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:<?= emailEsc($empresa['color']) ?>;">
                <tr>
                    <td align="center" style="padding:25px 20px;">
                        <a href="<?= emailEsc($empresa['web']) ?>" target="_blank" style="text-decoration:none;">
                            <img src="<?= emailEsc($empresa['logo']) ?>" alt="<?= emailEsc($empresa['nombre']) ?>" width="80" style="display:block;border:0;outline:none;text-decoration:none;max-width:200px;height:auto;">
                        </a>
                    </td>
                </tr>
            </table>

            <!-- CONTENIDO -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                <tr>
                    <td style="padding:25px 20px 10px 20px;font-family:<?= $fuente ?>;color:#4A4A4A;">
                        <?= $contenido ?>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:10px 20px 30px 20px;font-family:<?= $fuente ?>;font-size:14px;line-height:22px;color:#4A4A4A;">
                        <?php if ($empresa['correo'] !== ''): ?>
                            Si tienes preguntas o sugerencias, <a href="mailto:<?= emailEsc($empresa['correo']) ?>" style="color:<?= emailEsc($empresa['color']) ?>;">escríbenos</a>. ¡Estaremos encantados de ayudarte!
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <!-- PIE -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#333333;">
                <tr>
                    <td align="center" style="padding:30px 20px;font-family:<?= $fuente ?>;">
                        <?php if (count($redes) > 0): ?>
                            <p style="margin:0 0 15px 0;font-size:14px;">
                                <?php foreach ($redes as $i => $red): ?>
                                    <?= $i > 0 ? '&nbsp;&middot;&nbsp;' : '' ?><a href="<?= emailEsc($red['link']) ?>" target="_blank" style="color:#FFFFFF;text-decoration:underline;"><?= emailEsc($red['nombre']) ?></a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        <p style="margin:0;font-size:12px;line-height:18px;color:#BBBBBB;">No respondas este mensaje: fue generado automáticamente.</p>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>

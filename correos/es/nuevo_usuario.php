<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PhpMailer/src/PHPMailer.php';
require 'PhpMailer/src/SMTP.php';
require 'PhpMailer/src/Exception.php';

require_once "../funciones.php";
require_once "../clases/cl_empresas.php";

$cl_empresas = new cl_empresas(NULL);
$session = getSession();

if(isset($_GET['id']) && isset($_GET['pass'])){
  $id = $_GET['id'];
  $pass = $_GET['pass'];
  $info = $cl_empresas->get($id);

  if($info){
    $nombre = $info['nombre'];
    $correo = $info['representante_correo'];

  }else{
    
  }
}else{
  
}

error_reporting(0);

$logo = "https://digitalmindtec.com/images/dm_logo_mn.png";
$bgcolor = "#123597";
//$bgcolor = "#EB6341";
//$bgcolor = "#058720";

$html = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" style="width:100%;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;padding:0;Margin:0">
 <head> 
  <meta charset="UTF-8"> 
  <meta content="width=device-width, initial-scale=1" name="viewport"> 
  <meta name="x-apple-disable-message-reformatting"> 
  <meta http-equiv="X-UA-Compatible" content="IE=edge"> 
  <meta content="telephone=no" name="format-detection"> 
  <title>Registro</title> 
  <!--[if (mso 16)]><style type="text/css"> a {text-decoration: none;} </style><![endif]--> 
  <!--[if gte mso 9]><style>sup { font-size: 100% !important; }</style><![endif]--> 
  <!--[if !mso]><!-- --> 
  <link href="https://fonts.googleapis.com/css?family=Montserrat:500,800" rel="stylesheet"> 
  <!--<![endif]--> 
  <!--[if gte mso 9]>
<xml>
    <o:OfficeDocumentSettings>
    <o:AllowPNG></o:AllowPNG>
    <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
</xml>
<![endif]--> 
  <style type="text/css">
@media only screen and (max-width:600px) {u + #body { width:100vw!important } p, ul li, ol li, a { font-size:16px!important; line-height:150%!important } h1 { font-size:30px!important; text-align:center; line-height:120%!important } h2 { font-size:26px!important; text-align:center; line-height:120%!important } h3 { font-size:20px!important; text-align:center; line-height:120%!important } h1 a { font-size:30px!important } h2 a { font-size:26px!important } h3 a { font-size:20px!important } .es-menu td a { font-size:16px!important } .es-header-body p, .es-header-body ul li, .es-header-body ol li, .es-header-body a { font-size:16px!important } .es-footer-body p, .es-footer-body ul li, .es-footer-body ol li, .es-footer-body a { font-size:16px!important } .es-infoblock p, .es-infoblock ul li, .es-infoblock ol li, .es-infoblock a { font-size:12px!important } *[class="gmail-fix"] { display:none!important } .es-m-txt-c, .es-m-txt-c h1, .es-m-txt-c h2, .es-m-txt-c h3 { text-align:center!important } .es-m-txt-r, .es-m-txt-r h1, .es-m-txt-r h2, .es-m-txt-r h3 { text-align:right!important } .es-m-txt-l, .es-m-txt-l h1, .es-m-txt-l h2, .es-m-txt-l h3 { text-align:left!important } .es-m-txt-r img, .es-m-txt-c img, .es-m-txt-l img { display:inline!important } .es-button-border { display:block!important } a.es-button { font-size:16px!important; display:block!important; border-left-width:0px!important; border-right-width:0px!important } .es-btn-fw { border-width:10px 0px!important; text-align:center!important } .es-adaptive table, .es-btn-fw, .es-btn-fw-brdr, .es-left, .es-right { width:100%!important } .es-content table, .es-header table, .es-footer table, .es-content, .es-footer, .es-header { width:100%!important; max-width:600px!important } .es-adapt-td { display:block!important; width:100%!important } .adapt-img { width:100%!important; height:auto!important } .es-m-p0 { padding:0px!important } .es-m-p0r { padding-right:0px!important } .es-m-p0l { padding-left:0px!important } .es-m-p0t { padding-top:0px!important } .es-m-p0b { padding-bottom:0!important } .es-m-p20b { padding-bottom:20px!important } .es-mobile-hidden, .es-hidden { display:none!important } tr.es-desk-hidden, td.es-desk-hidden, table.es-desk-hidden { display:table-row!important; width:auto!important; overflow:visible!important; float:none!important; max-height:inherit!important; line-height:inherit!important } .es-desk-menu-hidden { display:table-cell!important } table.es-table-not-adapt, .esd-block-html table { width:auto!important } table.es-social { display:inline-block!important } table.es-social td { display:inline-block!important } }
#outlook a {
  padding:0;
}
.ExternalClass {
  width:100%;
}
.ExternalClass,
.ExternalClass p,
.ExternalClass span,
.ExternalClass font,
.ExternalClass td,
.ExternalClass div {
  line-height:100%;
}
.es-button {
  mso-style-priority:100!important;
  text-decoration:none!important;
}
a[x-apple-data-detectors] {
  color:inherit!important;
  text-decoration:none!important;
  font-size:inherit!important;
  font-family:inherit!important;
  font-weight:inherit!important;
  line-height:inherit!important;
}
.es-desk-hidden {
  display:none;
  float:left;
  overflow:hidden;
  width:0;
  max-height:0;
  line-height:0;
  mso-hide:all;
}
a.es-button:hover {
  border-color:#2CB543!important;
  background:#2CB543!important;
}
a.es-secondary:hover {
  border-color:#ffffff!important;
  background:#ffffff!important;
}
</style> 
 </head> 
 <body style="width:100%;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;padding:0;Margin:0"> 
  <div class="es-wrapper-color" style="background-color:#F7F7F7"> 
   <!--[if gte mso 9]>
      <v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
        <v:fill type="tile" color="#F7F7F7"></v:fill>
      </v:background>
    <![endif]--> 
   <table cellpadding="0" cellspacing="0" class="es-wrapper" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;padding:0;Margin:0;width:100%;height:100%;background-repeat:repeat;background-position:center top"> 
     <tr style="border-collapse:collapse"> 
      <td valign="top" style="padding:0;Margin:0"> 
       <table cellpadding="0" cellspacing="0" class="es-header" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%;background-color:#34265F;background-repeat:repeat;background-position:center bottom"> 
         <tr style="border-collapse:collapse"> 
          <td align="center" bgcolor="'.$bgcolor.'" style="padding:0;Margin:0;background-image:url(https://itouxr.stripocdn.email/content/guids/CABINET_3a7a698c62586f3eb3e12df4199718b8/images/6941564382201394.png);background-color:'.$bgcolor.';background-position:center bottom;background-repeat:repeat" background="https://itouxr.stripocdn.email/content/guids/CABINET_3a7a698c62586f3eb3e12df4199718b8/images/6941564382201394.png"> 
           <table class="es-header-body" cellspacing="0" cellpadding="0" align="center" bgcolor="transparent" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px"> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:15px;padding-right:15px;background-position:center bottom"> 
               <table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
               </table></td> 
             </tr> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="Margin:0;padding-top:20px;padding-left:20px;padding-right:20px;padding-bottom:25px;background-position:center bottom"> 
               <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tr style="border-collapse:collapse"> 
                  <td valign="top" align="center" style="padding:0;Margin:0;width:560px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-position:center bottom" role="presentation"> 
                     <tr style="border-collapse:collapse"> 
                      <td class="es-m-txt-c" align="center" style="padding:0;Margin:0;font-size:0px"><a href="https://digitalmindtec.com/" target="_blank" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;font-size:14px;text-decoration:underline;color:#FFFFFF"><img src="'.$logo.'" alt="Logo" title="logo" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="75"></a></td> 
                     </tr> 
                   </table></td> 
                 </tr> 
               </table></td> 
             </tr> 
           </table></td> 
         </tr> 
       </table> 
       <table cellpadding="0" cellspacing="0" class="es-content" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table bgcolor="#ffffff" class="es-content-body" align="center" cellpadding="0" cellspacing="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px"> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:30px;padding-right:30px;background-position:center bottom"> 
               <table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tr style="border-collapse:collapse"> 
                  <td align="center" valign="top" style="padding:0;Margin:0;width:540px"> 
                   <table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tr style="border-collapse:collapse"> 
                      <td align="left" style="padding:0;Margin:0;padding-bottom:5px"><h1 style="Margin:0;line-height:38px;mso-line-height-rule:exactly;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;font-size:32px;font-style:normal;font-weight:bold;color:#4A4A4A">Registro de Empresa</h1></td> 
                     </tr> 
                   </table></td> 
                 </tr> 
               </table></td> 
             </tr> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="Margin:0;padding-top:20px;padding-bottom:20px;padding-left:30px;padding-right:30px"> 
               <!--[if mso]></td><td style="width:20px"></td><td style="width:364px" valign="top"><![endif]--> 
               <table cellpadding="0" cellspacing="0" class="es-left" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left"> 
                 <tr style="border-collapse:collapse"> 
                  <td align="left" style="padding:0;Margin:0;width:364px"> 
                   <table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tr style="border-collapse:collapse"> 
                      <td align="left" class="es-m-txt-l" style="padding:0;Margin:0;padding-top:10px"><h3 style="Margin:0;line-height:20px;mso-line-height-rule:exactly;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;font-size:17px;font-style:normal;font-weight:bold;color:#4A4A4A">
                        ¡BIENVENIDO '.$nombre.'!</h3></td> 
                     </tr> 
                     <tr style="border-collapse:collapse"> 
                      <td align="left" style="padding:0;Margin:0;padding-top:10px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:17px;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;line-height:26px;color:#4A4A4A"></p></td> 
                     </tr> 
                   </table></td> 
                 </tr> 
               </table> 
               <!--[if mso]></td></tr></table><![endif]--></td> 
             </tr> 
           </table></td> 
         </tr> 
       </table> 
       <table cellpadding="0" cellspacing="0" class="es-content" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table bgcolor="#ffffff" class="es-content-body" align="center" cellpadding="0" cellspacing="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px"> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="Margin:0;padding-left:10px;padding-top:20px;padding-bottom:20px;padding-right:20px;background-position:left top;background-color:#FFFFFF;border-radius:13px" bgcolor="#ffffff"> 
               <table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tr style="border-collapse:collapse"> 
                  <td align="left" style="padding:0;Margin:0;width:570px"> 
                   <table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                     <tr style="border-collapse:collapse"> 
                      <td align="left" style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px"> 
                      
                        Tu Tienda en Línea ha sido registrada exitosamente, ¡felicidades!</b><br/>
                        <br/>
                        1. Accede a https://dashboard.mie-commerce.com/ <br/>
                        2. Escribe tu usuario y contraseña<br/>
                        <b>'.$correo.'</b><br/>
                        <b>'.$pass.'</b><br/>
                        3. Empieza a crear categorías, subir productos, crear promociones entre otras funciones. Estos tutoriales te podrán ayudar:<br/>
                        Creación de Sucursales: https://loom.com/</b><br/>
                        Creación de Categorías: https://loom.com/</b><br/>
                        Creación de Productos: https://loom.com/</b><br/>
                        Costos de envío:</b><br/>
                        Creación de usuarios:</b><br/>
                        Creación de promociones:</b><br/>
                        4. ¡Listo! Estamos del otro lado. </b><br/>
                        Escribe a este correo para alguna sugerencia o consulta</b><br/>
                        </br>
                        </br>
                        ¡Recuerda que estamos para ayudarte!
                        
                      </td> 
                     </tr> 
                   </table></td> 
                 </tr> 
               </table></td> 
             </tr> 
           </table></td> 
         </tr> 
       </table> 
       <table cellpadding="0" cellspacing="0" class="es-content" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-content-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px"> 
           </table></td> 
         </tr> 
       </table> 
       <table cellpadding="0" cellspacing="0" class="es-content" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table bgcolor="#ffffff" class="es-content-body" align="center" cellpadding="0" cellspacing="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px"> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="Margin:0;padding-top:15px;padding-bottom:25px;padding-left:30px;padding-right:30px;background-position:center bottom"> 
               <table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tr style="border-collapse:collapse"> 
                  <td align="center" valign="top" style="padding:0;Margin:0;width:540px"> 
                   <table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tr style="border-collapse:collapse"> 
                      
                     </tr> 
                   </table></td> 
                 </tr> 
               </table></td> 
             </tr> 
           </table></td> 
         </tr> 
       </table> 
       <table cellpadding="0" cellspacing="0" class="es-footer" align="center" background="https://itouxr.stripocdn.email/content/guids/CABINET_3a7a698c62586f3eb3e12df4199718b8/images/75021564382669317.png" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%;background-color:#F7F7F7;background-repeat:repeat;background-position:center top;background-image:url(https://itouxr.stripocdn.email/content/guids/CABINET_3a7a698c62586f3eb3e12df4199718b8/images/75021564382669317.png)"> 
         <tr style="border-collapse:collapse"> 
          <td align="center" bgcolor="transparent" style="padding:0;Margin:0;background-color:transparent;background-position:left top"> 
           <table bgcolor="transparent" class="es-footer-body" align="center" cellpadding="0" cellspacing="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px"> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="padding:0;Margin:0;padding-bottom:5px;padding-top:30px"> 
               <table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tr style="border-collapse:collapse"> 
                  <td align="center" valign="top" style="padding:0;Margin:0;width:600px"> 
                   <table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tr style="border-collapse:collapse"> 
                      <td align="center" height="3" style="padding:0;Margin:0"></td> 
                     </tr> 
                   </table></td> 
                 </tr> 
               </table></td> 
             </tr> 
           </table></td> 
         </tr> 
       </table> 
       <table cellpadding="0" cellspacing="0" class="es-content" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tr style="border-collapse:collapse"> 
          <td align="center" bgcolor="#333333" style="padding:0;Margin:0;background-color:#333333"> 
           <table bgcolor="#FFFFFF" class="es-content-body" align="center" cellpadding="0" cellspacing="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px"> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="padding:0;Margin:0;padding-bottom:30px;padding-top:40px"> 
               <table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tr style="border-collapse:collapse"> 
                  <td align="center" valign="top" style="padding:0;Margin:0;width:600px"> 
                   <table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tr style="border-collapse:collapse"> 
                      <td align="center" class="es-m-txt-c" style="padding:0;Margin:0;font-size:0"> 
                       <table cellpadding="0" cellspacing="0" class="es-table-not-adapt es-social" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                         <tr style="border-collapse:collapse"> 
                          <td align="center" valign="top" style="padding:0;Margin:0;padding-right:10px"><a target="_blank" href="https://www.facebook.com/digitalmindtec" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;font-size:16px;text-decoration:underline;color:#3B2495"><img title="Facebook" src="https://esputnik.com/content/stripostatic/assets/img/social-icons/logo-white/facebook-logo-white.png" alt="Fb" width="32" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic"></a></td> 
                          <td align="center" valign="top" style="padding:0;Margin:0;padding-right:10px"><a target="_blank" href="https://www.instagram.com/digitalmindtec/" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;font-size:16px;text-decoration:underline;color:#3B2495"><img title="Instagram" src="https://esputnik.com/content/stripostatic/assets/img/social-icons/logo-white/instagram-logo-white.png" alt="Ig" width="32" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic"></a></td> 
                         </tr> 
                       </table></td> 
                     </tr> 
                   </table></td> 
                 </tr> 
               </table></td> 
             </tr> 
             <tr style="border-collapse:collapse"> 
              <td align="left" style="padding:0;Margin:0;padding-bottom:30px;padding-left:30px;padding-right:30px;background-position:center bottom"> 
               <table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tr style="border-collapse:collapse"> 
                  <td align="center" valign="top" style="padding:0;Margin:0;width:540px"> 
                   <table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tr style="border-collapse:collapse"> 
                      <td align="center" class="es-infoblock" style="padding:0;Margin:0;line-height:14px;font-size:12px;color:#CCCCCC"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:12px;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;line-height:18px;color:#CCCCCC">No responder este mensaje porque fue generado automaticamente</p></td> 
                     </tr> 
                   </table></td> 
                 </tr> 
               </table></td> 
             </tr> 
           </table></td> 
         </tr> 
       </table></td> 
     </tr> 
   </table> 
  </div>  
 </body>
</html>';

if(isset($_GET['print'])){
  echo $html;
}else{
  
  $asunto = "FeedCrew - Registro de Empresa";
  //$nombre = "Juan Carlos";

  $mail = new PHPMailer();
  try {
      //Server settings
      $mail->SMTPDebug = 2; 
      // Enable verbose debug output
    
      //GMAIL
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = 'juankbastidasjuve@gmail.com';
      $mail->Password = 'fcjuventus'; 
      $mail->SMTPSecure = 'ssl';
      $mail->Port = 465;
        
      //Recipients
      $mail->setFrom('juankbastidasjuve@gmail.com', 'Juan Bastidas');
      $mail->addAddress($correo, html_entity_decode($nombre));
      $mail->addReplyTo('juankbastidasjuve@gmail.com', 'Juan Bastidas');
          
      //Content
      $mail->isHTML(true);
      $mail->CharSet = 'UTF-8';
      $mail->Subject = $asunto;
      $mail->Body    = $html;
      $mail->AltBody = 'DigitalMind';

      if (!$mail->send())
      {
          $return['success']= 0;
          $return['mensaje']= "Error al enviar el correo";
      }
      else
      {
        $return['success']= 1;
          $return['mensaje']= "Correo enviado correctamente";
      }
    } catch (Exception $e) {
      $return['success']= 0;
        $return['mensaje']= "Error al enviar el correo, Error: ";
  }

  header('Content-Type: application/json');
  echo json_encode($return);
}

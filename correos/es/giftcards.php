<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PhpMailer/src/PHPMailer.php';
require 'PhpMailer/src/SMTP.php';
require 'PhpMailer/src/Exception.php';

require_once "../funciones.php";
require_once "../clases/cl_usuarios.php";
require_once "../clases/cl_empresas.php";
require_once "../clases/cl_giftcards.php";
require_once "../clases/cl_sucursales.php";

require_once "configEmail.php";

$Clusuarios = new cl_usuarios(NULL);
$Clempresas = new cl_empresas(NULL);
$Clgiftcards = new cl_giftcards(NULL);
$Clsucursales = new cl_sucursales(NULL);


if(isset($_GET['codigo']) && isset($_GET['id'])){
    $codigo = $_GET['codigo'];
    $cod_empresa = $_GET['id'];
    $msn_user = $_GET['msj'];
    
    $empresa = $Clempresas->get($cod_empresa);
    if(!$empresa){
        $return['success'] = 0;
        $return['mensaje'] = "Empresa no existente";
        header('Content-Type: application/json');
        echo json_encode($return);
        exit;
    }
    $files = url_sistema.'assets/empresas/'.$empresa['alias'].'/';
    
    $name = $empresa['nombre'];
    $web = $empresa['url_web'];
    $logo = $files.$empresa['logo'];
    $bgcolor = $empresa['color'];
    $mailto = $empresa['correo'];
    $setFrom = $empresa['correo'];
    $setFrom = "info@mie-commerce.com";
    $descripcion = $empresa['description'];
    $telefono = $empresa['telefono'];
    $direccion = $empresa['direccion'];
    
    //MAPA SUCURSAL
    $cod_sucursal = $_GET['suc'];
    $sucursal = $Clsucursales->getInfo($cod_sucursal);
    $latitud = $sucursal['latitud'];
    $longitud = $sucursal['longitud'];
    
    /*$sucursales = $Clsucursales->listaByEmpresa($cod_empresa);
    for($i=0; $i<1; $i++){
        if($sucursales[0]['estado'] == "A")
        {
            $latitud = $sucursales[0]['latitud'];
            $longitud = $sucursales[0]['longitud'];
            echo $sucursales[0]['cod_sucursal'];
        }
    }*/
    
    /*foreach($sucursales as $suc){
        if($suc['estado'] == "A")
        {
            $latitud = $suc['latitud'];
            $longitud = $suc['longitud'];
            echo $suc['nombre'];
        }
    }*/
    //$latitud = -2.130158;
    //$longitud = -79.8989227;
    
    //GIFT CARDS
    $Clgiftcards->getGiftByCode($codigo, $giftcard);
    $cod_giftcard = $giftcard['cod_giftcard'];
    $nr = explode(" ", $giftcard['nombre']);
    $nombre_receptor = $nr[0];
    //$detalle = $giftcard['detalle'];
    $monto = $giftcard['monto'];
    
    $Clgiftcards->getArray($cod_giftcard, $gift);
    $img_gift = $files.$gift['imagen'];
    $mensaje = "Una personal muy especial te ha regalado una Gift Card de $".$monto." y te ha dejado el siguiente mensaje: <br>".$msn_user;
    
    // REDES SOCIALES
    $redesSociales = $Clempresas->getAllRedesSociales($cod_empresa);
    $Linkfacebook = "";
    $Linkinstagram = "";
    foreach($redesSociales as $redes){
        if($redes['codigo'] == "FACEBOOK"){
            $Linkfacebook =  '<td align="center" valign="top" style="padding:0;Margin:0;padding-right:10px"><a target="_blank" href="'.$redes['descripcion'].'" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;font-size:16px;text-decoration:underline;color:#3B2495"><img title="Facebook" src="https://esputnik.com/content/stripostatic/assets/img/social-icons/logo-white/facebook-logo-white.png" alt="Fb" width="32" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic"></a></td>';
        }
        else if($redes['codigo'] == "INSTAGRAM"){
             $Linkinstagram = '<td align="center" valign="top" style="padding:0;Margin:0;padding-right:10px"><a target="_blank" href="'.$redes['descripcion'].'" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:Montserrat, Helvetica, Roboto, Arial, sans-serif;font-size:16px;text-decoration:underline;color:#3B2495"><img title="Instagram" src="https://esputnik.com/content/stripostatic/assets/img/social-icons/logo-white/instagram-logo-white.png" alt="Ig" width="32" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic"></a></td>';
        }
    }
    
    //DATOS DEL USUARIO RECEPTOR
    $correo_usuario_receptor = $giftcard['correo'];
}

error_reporting(0);

$html='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<!-- saved from url=(0068)file:///C:/Users/User/Desktop/Nuevo%20correo%20electr%C3%B3nico.html -->
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" style="width:100%;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;padding:0;Margin:0">
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"> 
   
  <meta content="width=device-width, initial-scale=1" name="viewport"> 
  <meta name="x-apple-disable-message-reformatting"> 
  <meta http-equiv="X-UA-Compatible" content="IE=edge"> 
  <meta content="telephone=no" name="format-detection"> 
  <title>Gift Cards</title> 
  <!--[if (mso 16)]>
    <style type="text/css">
    a {text-decoration: none;}
    </style>
    <![endif]--> 
  <!--[if gte mso 9]><style>sup { font-size: 100% !important; }</style><![endif]--> 
  <!--[if gte mso 9]>
<xml>
    <o:OfficeDocumentSettings>
    <o:AllowPNG></o:AllowPNG>
    <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
</xml>
<![endif]--> 
  <!--[if !mso]><!-- --> 
  <link href="./assets/css" rel="stylesheet"> 
  <!--<![endif]--> 
  <style type="text/css">
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
.es-button-border:hover {
	background:#7dbf44!important;
}
td .es-button-border:hover a.es-button-1 {
	background:#7dbf44!important;
	border-color:#7dbf44!important;
}
td .es-button-border-2:hover {
	background:#7dbf44!important;
}

.color-empresa{
    color: '.$bgcolor.' !important;
}

.bg-color-empresa > tr > td{
    background-color: '.$bgcolor.' !important;
}

.btn-color-empresa{
    background-color: '.$bgcolor.' !important;
    border-color: '.$bgcolor.' !important;
}
@media only screen and (max-width:600px) {p, ul li, ol li, a { font-size:14px!important; line-height:150%!important } h1 { font-size:25px!important; text-align:center; line-height:120%!important } h2 { font-size:22px!important; text-align:center; line-height:120%!important } h3 { font-size:16px!important; text-align:center; line-height:120%!important } h1 a { font-size:25px!important } h2 a { font-size:22px!important } h3 a { font-size:16px!important } .es-menu td a { font-size:16px!important } .es-header-body p, .es-header-body ul li, .es-header-body ol li, .es-header-body a { font-size:16px!important } .es-footer-body p, .es-footer-body ul li, .es-footer-body ol li, .es-footer-body a { font-size:14px!important } .es-infoblock p, .es-infoblock ul li, .es-infoblock ol li, .es-infoblock a { font-size:12px!important } *[class="gmail-fix"] { display:none!important } .es-m-txt-c, .es-m-txt-c h1, .es-m-txt-c h2, .es-m-txt-c h3 { text-align:center!important } .es-m-txt-r, .es-m-txt-r h1, .es-m-txt-r h2, .es-m-txt-r h3 { text-align:right!important } .es-m-txt-l, .es-m-txt-l h1, .es-m-txt-l h2, .es-m-txt-l h3 { text-align:left!important } .es-m-txt-r img, .es-m-txt-c img, .es-m-txt-l img { display:inline!important } .es-button-border { display:block!important } .es-btn-fw { border-width:10px 0px!important; text-align:center!important } .es-adaptive table, .es-btn-fw, .es-btn-fw-brdr, .es-left, .es-right { width:100%!important } .es-content table, .es-header table, .es-footer table, .es-content, .es-footer, .es-header { width:100%!important; max-width:600px!important } .es-adapt-td { display:block!important; width:100%!important } .adapt-img { width:100%!important; height:auto!important } .es-m-p0 { padding:0px!important } .es-m-p0r { padding-right:0px!important } .es-m-p0l { padding-left:0px!important } .es-m-p0t { padding-top:0px!important } .es-m-p0b { padding-bottom:0!important } .es-m-p20b { padding-bottom:20px!important } .es-mobile-hidden, .es-hidden { display:none!important } tr.es-desk-hidden, td.es-desk-hidden, table.es-desk-hidden { width:auto!important; overflow:visible!important; float:none!important; max-height:inherit!important; line-height:inherit!important } tr.es-desk-hidden { display:table-row!important } table.es-desk-hidden { display:table!important } td.es-desk-menu-hidden { display:table-cell!important } table.es-table-not-adapt, .esd-block-html table { width:auto!important } table.es-social { display:inline-block!important } table.es-social td { display:inline-block!important } a.es-button, button.es-button { font-size:20px!important; display:block!important; border-left-width:0px!important; border-right-width:0px!important } }
</style> 
 </head> 
 <body style="width:100%;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;padding:0;Margin:0"> 
  <div class="es-wrapper-color" style="background-color:#F6F6F6"> 
   <!--[if gte mso 9]>
			<v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
				<v:fill type="tile" color="#f6f6f6"></v:fill>
			</v:background>
		<![endif]--> 
   <table class="es-wrapper" width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;padding:0;Margin:0;width:100%;height:100%;background-repeat:repeat;background-position:center top"> 
     <tbody><tr style="border-collapse:collapse"> 
      <td valign="top" style="padding:0;Margin:0"> 
       <table cellpadding="0" cellspacing="0" class="es-content" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tbody><tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-content-body" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px" cellspacing="0" cellpadding="0" bgcolor="#FFFFFF" align="center"> 
             <tbody>
                 <tr style="border-collapse:collapse"> 
             </tr> 
           </tbody></table></td> 
         </tr> 
       </tbody></table> 
       <table cellpadding="0" cellspacing="0" class="es-header" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%;background-color:transparent;background-repeat:repeat;background-position:center top"> 
         <tbody><tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-header-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px"> 
             <tbody><tr style="border-collapse:collapse"> 
              <td style="Margin:0;padding-bottom:10px;padding-top:20px;padding-left:20px;padding-right:20px;background-position:center center" align="left"> 
               <!--[if mso]><table style="width:560px" cellpadding="0" cellspacing="0"><tr><td style="width:270px" valign="top"><![endif]--> 
               <table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td class="es-m-p20b" align="left" style="padding:0;Margin:0;width:270px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td align="left" style="padding:0;Margin:0;padding-bottom:5px;font-size:0"><a target="_blank" href="'.$web.'" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;font-size:16px;text-decoration:underline;color:#659C35"><img src="'.$logo.'" alt="Logo" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" class="adapt-img" width="125"></a></td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table> 
               <!--[if mso]></td><td style="width:20px"></td><td style="width:270px" valign="top"><![endif]--> 
               <table class="es-right" cellspacing="0" cellpadding="0" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td align="left" style="padding:0;Margin:0;width:270px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td style="padding:0;Margin:0"> 
                       </td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table> 
               <!--[if mso]></td></tr></table><![endif]--></td> 
             </tr> 
           </tbody></table></td> 
         </tr> 
       </tbody></table> 
       
       <table class="es-content" cellspacing="0" cellpadding="0" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tbody><tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-content-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px"> 
             <tbody><tr style="border-collapse:collapse"> 
              <td align="left" style="Margin:0;padding-bottom:15px;padding-top:20px;padding-left:20px;padding-right:20px"> 
               <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td valign="top" align="center" style="padding:0;Margin:0;width:560px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td align="center" style="padding:0;Margin:0"><h2 class="color-empresa" style="Margin:0;line-height:31px;mso-line-height-rule:exactly;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;font-size:26px;font-style:normal;font-weight:bold;color:#659C35">¡FELICIDADES '.strtoupper($nombre_receptor).'!</h2></td> 
                     </tr> 
                     <tr style="border-collapse:collapse"> 
                      <td align="center" style="padding:0;Margin:0;padding-top:10px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:14px;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;line-height:21px;color:#333333"> '.$mensaje.' </p></td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table></td> 
             </tr> 
           </tbody></table></td> 
         </tr> 
       </tbody></table> 

       <table class="es-content" cellspacing="0" cellpadding="0" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tbody><tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-content-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px"> 
             <tbody><tr style="border-collapse:collapse"> 
              <td style="padding:0;Margin:0;background-position:center center" align="left"> 
               <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td valign="top" align="center" style="padding:0;Margin:0;width:600px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td style="padding:0;Margin:0;position:relative" align="center"><img class="adapt-img" src="'.$img_gift.'" alt="GiftCard" title="" width="400" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic"></td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table></td> 
             </tr> 
           </tbody></table></td> 
         </tr> 
       </tbody></table> 

       <table class="es-content" cellspacing="0" cellpadding="0" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tbody><tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-content-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px"> 
             <tbody><tr style="border-collapse:collapse"> 
              <td style="Margin:0;padding-left:20px;padding-right:20px;padding-top:30px;padding-bottom:30px;background-position:left top" align="left"> 
               <!--[if mso]><table style="width:560px" cellpadding="0" cellspacing="0"><tr><td style="width:270px" valign="top"><![endif]--> 
               <table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td class="es-m-p20b" align="left" style="padding:0;Margin:0;width:270px"> 
                   <table style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-position:center center" width="100%" cellspacing="0" cellpadding="0" role="presentation"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td align="left" style="padding:0;Margin:0"><h4 class="color-empresa" style="Margin:0;line-height:120%;mso-line-height-rule:exactly;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;color:#659C35">Cont&aacute;ctanos:</h4></td> 
                     </tr> 
                     <tr style="border-collapse:collapse"> 
                      <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-bottom:15px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:14px;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;line-height:21px;color:#333333">'.$descripcion.'</p></td> 
                     </tr> 
                     <tr style="border-collapse:collapse"> 
                      <td style="padding:0;Margin:0"> 
                       <table class="es-table-not-adapt" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                         <tbody><tr style="border-collapse:collapse"> 
                          <td valign="top" align="left" style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px;padding-right:10px;font-size:0"><img src="https://dashboard.mie-commerce.com/correosFront/assets/30981556869899567.png" alt style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="16"></td> 
                          <td align="left" style="padding:0;Margin:0"> 
                           <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                             <tbody><tr style="border-collapse:collapse"> 
                              <td align="left" style="padding:0;Margin:0"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:14px;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;line-height:21px;color:#333333"><a target="_blank" href="mailto:'.$mailto.'" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;font-size:14px;text-decoration:none;color:#333333">'.$mailto.'</a></p></td> 
                             </tr> 
                           </tbody></table></td> 
                         </tr> 
                         <tr style="border-collapse:collapse"> 
                          <td valign="top" align="left" style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px;padding-right:10px;font-size:0"><img src="https://dashboard.mie-commerce.com/correosFront/assets/58031556869792224.png" alt style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="16"></td> 
                          <td align="left" style="padding:0;Margin:0"> 
                           <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                             <tbody><tr style="border-collapse:collapse"> 
                              <td align="left" style="padding:0;Margin:0"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:14px;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;line-height:21px;color:#333333"><a target="_blank" href="tel:'.$telefono.'" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;font-size:14px;text-decoration:none;color:#333333">'.$telefono.'</a></p></td> 
                             </tr> 
                           </tbody></table></td> 
                         </tr> 
                         <tr style="border-collapse:collapse"> 
                          <td valign="top" align="left" style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px;padding-right:10px;font-size:0"><img src="https://dashboard.mie-commerce.com/correosFront/assets/78111556870146007.png" alt style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="16"></td> 
                          <td align="left" style="padding:0;Margin:0"> 
                           <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                             <tbody><tr style="border-collapse:collapse"> 
                              <td align="left" style="padding:0;Margin:0"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:14px;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;line-height:21px;color:#333333">'.$direccion.'</p></td> 
                             </tr> 
                           </tbody></table></td> 
                         </tr> 
                       </tbody></table></td> 
                     </tr> 
                     <tr style="border-collapse:collapse"> 
                      <td align="left" style="padding:0;Margin:0;padding-top:15px"><span class="es-button-border bg-color-empresa" style="border-style:solid;border-color:#2CB543;background:#659C35;border-width:0px;display:inline-block;border-radius:0px;width:auto"><a href="'.$web.'" class="es-button bg-color-empresa btn-color-empresa" target="_blank" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:roboto, &#39;helvetica neue&#39;, helvetica, arial, sans-serif;font-size:18px;color:#FFFFFF;border-style:solid;border-color:#659C35;border-width:10px 20px 10px 20px;display:inline-block;background:#659C35;border-radius:0px;font-weight:normal;font-style:normal;line-height:22px;width:auto;text-align:center">Ir al sitio</a></span></td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table> 
               <!--[if mso]></td><td style="width:20px"></td><td style="width:270px" valign="top"><![endif]--> 
               <table class="es-right" cellspacing="0" cellpadding="0" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td align="left" style="padding:0;Margin:0;width:270px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td align="center" style="padding:0;Margin:0;font-size:0"><a target="_blank" href="'.$mailto.'" style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, &#39;helvetica neue&#39;, helvetica, sans-serif;font-size:14px;text-decoration:underline;color:#2CB543"><img class="adapt-img" src="https://maps.googleapis.com/maps/api/staticmap?center='.$latitud.','.$longitud.'&markers=color:red%7Clabel:M%7C'.$latitud.','.$longitud.'&zoom=15&size=270x180&key=AIzaSyAHa67r_2hPqR_URtU8zsibmJx9Ahq7yGQ&libraries=places" alt="mapa" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="270"></a></td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table> 
               <!--[if mso]></td></tr></table><![endif]--></td> 
             </tr> 
           </tbody></table></td> 
         </tr> 
       </tbody></table> 
       <table cellpadding="0" cellspacing="0" class="es-footer" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%;background-color:transparent;background-repeat:repeat;background-position:center top"> 
         <tbody><tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-footer-body" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#333333;width:600px" cellspacing="0" cellpadding="0" bgcolor="#333333" align="center"> 
             <tbody class="bg-color-empresa"><tr style="border-collapse:collapse"> 
              <td style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px;background-position:center center;background-color:#659C35" bgcolor="#659C35" align="left"> 
               <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td valign="top" align="center" style="padding:0;Margin:0;width:560px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td style="padding:0;Margin:0"> 
                        </td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table></td> 
             </tr> 
             <tr style="border-collapse:collapse"> 
              <td style="Margin:0;padding-bottom:15px;padding-top:20px;padding-left:20px;padding-right:20px;background-position:center center;background-color:#659C35" bgcolor="#659C35" align="left"> 
               <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td valign="top" align="center" style="padding:0;Margin:0;width:560px"> 
                   <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                     <tbody><tr style="border-collapse:collapse"> 
                      <td align="center" style="padding:0;Margin:0;padding-bottom:15px;font-size:0"> 
                       <table class="es-table-not-adapt es-social" cellspacing="0" cellpadding="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                         <tbody><tr style="border-collapse:collapse"> 
                          '.$Linkfacebook.'
                          '.$Linkinstagram.'
                          <!--<td valign="top" align="center" style="padding:0;Margin:0;padding-right:15px"><img title="Twitter" src="./assets/twitter-circle-white.png" alt="Tw" width="32" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic"></td> 
                          <td valign="top" align="center" style="padding:0;Margin:0"><img title="Youtube" src="./assets/youtube-circle-white.png" alt="Yt" width="32" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic"></td> -->
                         </tr> 
                       </tbody></table></td> 
                     </tr> 
                   </tbody></table></td> 
                 </tr> 
               </tbody></table></td> 
             </tr> 
           </tbody></table></td> 
         </tr> 
       </tbody></table> 
       <table class="es-content" cellspacing="0" cellpadding="0" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%"> 
         <tbody><tr style="border-collapse:collapse"> 
          <td align="center" style="padding:0;Margin:0"> 
           <table class="es-content-body" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px" cellspacing="0" cellpadding="0" bgcolor="transparent" align="center"> 
             <tbody><tr style="border-collapse:collapse"> 
              <td style="Margin:0;padding-left:20px;padding-right:20px;padding-top:30px;padding-bottom:30px;background-position:center center" align="left"> 
               <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px"> 
                 <tbody><tr style="border-collapse:collapse"> 
                  <td valign="top" align="center" style="padding:0;Margin:0;width:560px"> </td> 
                 </tr> 
               </tbody></table></td> 
             </tr> 
           </tbody></table></td> 
         </tr> 
       </tbody></table></td> 
     </tr> 
   </tbody></table> 
  </div>  
 
</body></html>';

if(isset($_GET['print'])){
  echo $html;
}else{
  $asunto = $name." - Gift Card Recibida";

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
      $correoReplyTo = "info@mie-commerce.com";
      $mail->setFrom($setFrom, $name);
      $mail->addAddress($correo_usuario_receptor, html_entity_decode($nombre_receptor));
      $mail->addReplyTo($correoReplyTo, $name);
          
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
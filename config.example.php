<?php
//BASE DE DATOS
// define('servidor','192.254.236.69');
define('servidor','localhost');
define('db','database_name');
define('usuario','root');
define('contrasena','');

//FUNCIONALIDAD DEL SISTEMA
define('api_version','v10');
define('name_session','ADMIN_FEED_CREW');
define('DURACION_SESION','7200'); //2 horas
define('url_resource','https://dashboard.mie-commerce.com/assets/resource/api/');
// define('url_sistema','https://dashboard.mie-commerce.com/');
define('url_sistema','https://digitalmindtec.com/');
define('url_upload','/home1/digitalmind/dashboard.mie-commerce.com/');
define('url_api','https://api.mie-commerce.com/taste-front/'.api_version.'/');
define('ENVIRONMENT','development'); //development  or production
define('LOGIN_EMAIL_NUM_DIGITS',4);

//SERVIDOR DE CORREO
define('host','smtp.gmail.com');
define('SMTPAuth',true);

define('username','');
define('pass','');
define('SMTPSecure','ssl');
define('port','465');
define('correoReplyTo','');
define('setFromDefault','');

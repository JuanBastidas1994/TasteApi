<?php
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/.env');

define('servidor', env('DB_HOST', '127.0.0.1'));
define('db', env('DB_DATABASE', 'jc_taste'));
define('usuario', env('DB_USERNAME', 'root'));
define('contrasena', env('DB_PASSWORD', ''));

define('api_version', env('API_VERSION', 'v2'));
define('url_resource', env('URL_RESOURCE', 'https://tastedashboard.test/assets/resource/api/'));
define('url_sistema', env('URL_SISTEMA', 'https://tastedashboard.test/'));
define('url_upload', env('URL_UPLOAD', ''));
define('url_api', env('URL_API', 'https://tasteapi.test/'));
define('ENVIRONMENT', env('APP_ENV', 'development'));
define('LOGIN_EMAIL_NUM_DIGITS', (int) env('LOGIN_EMAIL_NUM_DIGITS', 4));

define('host', env('MAIL_HOST', '127.0.0.1'));
define('SMTPAuth', env('MAIL_AUTH', false));
define('username', env('MAIL_USERNAME', ''));
define('pass', env('MAIL_PASSWORD', ''));
define('SMTPSecure', env('MAIL_ENCRYPTION', ''));
define('port', env('MAIL_PORT', '2525'));
define('correoReplyTo', env('MAIL_REPLY_TO', ''));
define('setFromDefault', env('MAIL_FROM', ''));

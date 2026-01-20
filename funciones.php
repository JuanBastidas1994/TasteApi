<?php
require_once "config.php";
ob_start();
session_start();

if(ENVIRONMENT == "production"){
  ini_set('display_errors', 0);
  error_reporting(0);
}else{
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}

require_once "conexion.php";

function verificateWs(&$codigo)
{
  $Allheaders = getallheaders();
  if (array_key_exists("Api-Key",$Allheaders)){
    $query = "SELECT * FROM tb_empresas WHERE api_key = '".$Allheaders['Api-Key']."'";
    $codigo = Conexion::buscarRegistro($query);
    if($codigo)
      return true;
    else
      return false;
  }
  else
    return false;
}

function getUserHeader(){
  $Allheaders = getallheaders();
  if(array_key_exists("User-Id",$Allheaders)){
    $user_id = intval($Allheaders['User-Id']);
    if($user_id > 0)
      return $user_id;
    else
      return null;
  }
  return null;
}

function getUserDevice(){
  $Allheaders = getallheaders();
  if(array_key_exists("User-Device",$Allheaders)){
    return $Allheaders['User-Device'];
  }
  return null;
}

function encrypt_decrypt($action, $string) {
    $output = false;
    $encrypt_method = "AES-128-CBC";
    $secret_key = '1234567890123456';
    $secret_iv = '1234567890123456';
  
    if (strlen($secret_key) == 16){
      $encrypt_method = "AES-128-CBC";
    }else{
      $encrypt_method = "AES-256-CBC";
    }

    if ( $action == 'encrypt' ) {
        $output = openssl_encrypt($string, $encrypt_method, $secret_key, 0,$secret_iv);
        //$output is base64 encoded automatically!
    } else if( $action == 'decrypt' ) {
        $output = openssl_decrypt($string, $encrypt_method, $secret_key, 0,$secret_iv);
        //$string must be base64 encoded!
    }
    return $output;
}

function getFirstSucursal()
{
    $data = Conexion::buscarRegistro("SELECT * FROM tb_sucursales WHERE cod_empresa = ".cod_empresa." AND estado = 'A' ORDER BY cod_sucursal ASC LIMIT 0,1");
    if($data){
    	return $data['cod_sucursal'];
    }else{
    	return 0;
    }
}

function setCupon($userId, $tipo){
    $query = "SELECT * FROM tb_cupones WHERE tipo = '$tipo' AND estado = 'A' AND cod_empresa = ".cod_empresa;
    $cupon = Conexion::buscarRegistro($query);
    if($cupon){
        $cod_cupon = $cupon['cod_cupon'];
        $dias = $cupon['cantidad_dias_disponibles'];
        $query = "INSERT INTO tb_cupones_usuarios(cod_cupon, cod_usuario, fecha_creacion, fecha_caducidad, estado)";
        $query.= "VALUES($cod_cupon, $userId, NOW(), DATE_ADD(NOW(), INTERVAL $dias DAY), 'ACTIVO')";
        Conexion::ejecutar($query,NULL);
    }
}

function build_sorter($key) {
  return function ($a, $b) use ($key) {
     return strnatcmp($a[$key], $b[$key]);
  };
}

function sanitizeString($string) {
    $string = str_replace("\\", "\\\\", $string);  // Escapar barras invertidas
    $string = str_replace("\"", "\\\"", $string); // Escapar comillas dobles
    $string = str_replace("\n", "\\n", $string);  // Escapar saltos de línea
    $string = str_replace("\r", "\\r", $string);  // Escapar retorno de carro
    return $string;
}


function fecha()
{
	date_default_timezone_set('America/Guayaquil');
	$time = time();
	$fecha = date("Y-m-d H:i:s", $time);	//FECHA Y HORA ACTUAL
	return $fecha;
}

 function fecha_only()
{
	date_default_timezone_set('America/Guayaquil');
	$time = time();
	$fecha = date("Y-m-d", $time);	//FECHA Y HORA ACTUAL
	return $fecha;
}

function fechaFormat($format)
{
	date_default_timezone_set('America/Guayaquil');
	$time = time();
	$fecha = date($format, $time);	//FECHA Y HORA ACTUAL
	return $fecha;
}

function fechaToFormat($fecha, $format)
{
	date_default_timezone_set('America/Guayaquil');
	$fecha = date($format, strtotime($fecha));	//FECHA Y HORA ACTUAL
	return $fecha;
}

function dayOfWeek($fecha)
{
  date_default_timezone_set('America/Guayaquil');
  //$time = time();
  $dia = date("N", strtotime($fecha));  //FECHA Y HORA ACTUAL
  return $dia;
}

function hora()
{
  date_default_timezone_set('America/Guayaquil');
  $time = time();
  $hora = date("H:i:s", $time);  //FECHA Y HORA ACTUAL
  return $hora;
}

function hora_only()
{
  date_default_timezone_set('America/Guayaquil');
  $time = time();
  $hora = date("H:i", $time);  //FECHA Y HORA ACTUAL
  return $hora;
}

function hora_create($time) {
  $f = strtotime($time);
  $time = date("H:i:s", $f);
  return $time;
}


function getNextInterval(int $interval = 5): string 
{
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Guayaquil'));
    $minutes = (int)$now->format('i');
    $addMinutes = $interval - ($minutes % $interval);
    
    $nextTime = $now->modify("+{$addMinutes} minutes")
                    ->setTime(
                        (int)$now->modify("+{$addMinutes} minutes")->format('H'), 
                        (int)$now->modify("+{$addMinutes} minutes")->format('i'), 
                        0
                    );

    return $nextTime->format('H:i');
}

function horaFormat($hora){
    $split = explode(":", $hora);
    return $split[0].":".$split[1];
}

function AddIntervalo($datetime, $intervalo){
    list($h, $m, $s) = explode(':', $intervalo);
    $intervalo_minutos = ($h*60) + $m;

    list($dia, $hora) = explode(' ', $datetime);
    $nuevaHora = strtotime ( $intervalo_minutos.' minute' , strtotime ( $hora ) ) ;
    $nuevaHora = date ( 'H:i:s' , $nuevaHora);

    return $dia." ".$nuevaHora;
}

function hoursAgo($fecha, $min=0){
    $date = new DateTime($fecha);
    $now = new DateTime();
    $diff = $now->diff($date);
    $dia = $diff->format('%d');
    if($dia>$min){
        return fechaLatinoShort($fecha);
    }
    $hora = $diff->format('%h');
    if($hora == 0)
        return $diff->format('Hace %i minutos');
    else
        return $diff->format('Hace %h horas %i minutos');
}

function minutesRestantes($fecha_expira, $min=0){
  $expira = new DateTime($fecha_expira);
  $now = new DateTime();
  $diff = $now->diff($expira);
  $hora = $diff->format('%h');
  return $diff->format('Expira en %i minutos');
}

function minutesRestantes2($hora_inicio, $hora_fin){
  $horaInicio = new DateTime($hora_inicio);
  $horaTermino = new DateTime($hora_fin);
  
  $interval = $horaInicio->diff($horaTermino);
  $hours = str_pad($interval->format('%H'), 2, "0", STR_PAD_LEFT);
  $minutes = str_pad($interval->format('%i'), 2, "0", STR_PAD_LEFT);
  $seconds = str_pad($interval->format('%s'), 2, "0", STR_PAD_LEFT);
  $timeRemaining = $hours.":".$minutes.":".$seconds;
  return $timeRemaining;
}

function diasRestantes($fecha_expira){
    $expira = new DateTime($fecha_expira);
    $now = new DateTime();
    $diff = $now->diff($expira);
    return $diff->days;
}

function diasdelMes()
{
  date_default_timezone_set('America/Guayaquil');
  $time = time();
  $mes = date("m", $time);
  $year = date("Y", $time);
  return cal_days_in_month(CAL_GREGORIAN, $mes, $year);
}

function diasdelMesRol($mes)
{
 
  $mes_anio= explode("-",$mes);
  return cal_days_in_month(CAL_GREGORIAN, $mes_anio[1], $mes_anio[0]);
}

function mesTextOnly($mes = null)
{
  if($mes == null){
    date_default_timezone_set('America/Guayaquil');
    $time = time();
    $mes = date("n", $time);
  }
    
  switch ($mes) {
    case 1: return "Enero";
    case 2: return "Febrero";
    case 3: return "Marzo";
    case 4: return "Abril";
    case 5: return "Mayo";
    case 6: return "Junio";
    case 7: return "Julio";
    case 8: return "Agosto";
    case 9: return "Septiembre";
    case 10: return "Octubre";
    case 11: return "Noviembre";
    case 12: return "Diciembre";
  }
  return $mes;
}

function getYear()
{
  date_default_timezone_set('America/Guayaquil');
  $time = time();
  $mes = date("Y", $time);
  return $mes;
}

 function fileActual()
 {
     $data = explode('/', $_SERVER['PHP_SELF']);
     return $data[count($data)-1];
 }
 
 
 function create_slug($string){
    $slug = preg_replace('/[^A-Za-z0-9-]+/','-',$string);
    $slug = strtolower($slug);
    return $slug;
}

function dateTimeLatino($dia){
    $fecha = substr($dia, 0, 10);
    $hora = substr($dia, 11, 5);
    return fechaLatinoShortWeekday($fecha)." a las ".$hora;
}

function fechaLatinoShortWeekday($fecha){
  $fecha = substr($fecha, 0, 10);
  $weekday = date('N', strtotime($fecha));
  $numeroDia = date('d', strtotime($fecha));
  $dia = date('l', strtotime($fecha));
  $mes = date('F', strtotime($fecha));
  $anio = date('Y', strtotime($fecha));

  $meses_ES = array("Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic");
  $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
  $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
  
  $nombreWeek = str_replace(array("1","2","3","4","5","6","7"),array("Lun.","Mar.","Mie.","Jue.","Vie.","Sab.","Dom."), $weekday);
  return "$nombreWeek $numeroDia $nombreMes, $anio";
}

function fechaLatinoSplit($fecha){
  $fecha = substr($fecha, 0, 10);
  $numeroDia = date('d', strtotime($fecha));
  $dia = date('l', strtotime($fecha));
  $mes = date('F', strtotime($fecha));
  $anio = date('Y', strtotime($fecha));

  $meses_ES = array("Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic");
  $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
  $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
    return [
        'day' => $numeroDia,
        'month' => $nombreMes,
        'year' => $anio
    ];
}
 
 
function fechaLatinoShort($fecha){
  $fecha = substr($fecha, 0, 10);
  $numeroDia = date('d', strtotime($fecha));
  $dia = date('l', strtotime($fecha));
  $mes = date('F', strtotime($fecha));
  $anio = date('Y', strtotime($fecha));

  $meses_ES = array("Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic");
  $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
  $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
  return "$nombreMes $numeroDia, $anio";
}

 function fechaLatino($fecha) {
  $fecha = substr($fecha, 0, 10);
  $numeroDia = date('d', strtotime($fecha));
  $dia = date('l', strtotime($fecha));
  $mes = date('F', strtotime($fecha));
  $anio = date('Y', strtotime($fecha));
  $dias_ES = array("Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado", "Domingo");
  $dias_EN = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday");
  $nombredia = str_replace($dias_EN, $dias_ES, $dia);
  $meses_ES = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
  $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
  $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
  return $nombredia.", ".$numeroDia." de ".$nombreMes." de ".$anio;
}

function Weekday($fecha){
    $weekday = date('N', strtotime($fecha));
    $nombreWeek = str_replace(array("1","2","3","4","5","6","7"),array("Lunes","Martes","Miércoles","Jueves","Viernes","Sábado","Domingo"), $weekday);
    return $nombreWeek;
}

function getEstado($estado){
	switch ($estado) {
    case 'A': return "Activo";
    case 'I': return "Inactivo";
    case 'D': return "Eliminado";
  }
  return $estado;
}

function datetime_format(){
	date_default_timezone_set('America/Guayaquil');
	$time = time();
	$fecha = date("Y_m_d_H_i_s", $time);	//FECHA Y HORA ACTUAL
	return $fecha;
}

function datetime_format2(){
	date_default_timezone_set('America/Guayaquil');
	$time = time();
	$fecha = date("YmdHis", $time);	//FECHA Y HORA ACTUAL
	return $fecha;
}


function validar_correo($email)
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    else
        return false;
}

function fechaSignos()
{
  date_default_timezone_set('America/Guayaquil');
  $time = time();
  $fecha = date("YmdHis", $time);  //FECHA Y HORA ACTUAL
  return $fecha;
}

function get_client_ip_server() {
      $ipaddress = '';
      if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
      return $ipaddress;
  }

function passRandom(){
  $an = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
  $su = strlen($an) - 1;
  return  substr($an, rand(0, $su-1), 1) .
      substr($an, rand(0, $su), 1) .
      substr($an, rand(0, $su), 1) .
      substr($an, rand(0, $su), 1) .
      substr($an, rand(0, $su), 1) .
      substr($an, rand(0, $su), 1) .
      substr($an, rand(0, $su), 1) .
      substr($an, rand(0, $su), 1);
}

function passRandom2() {
  $letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  $numbers = "0123456789";
  $lettersLength = strlen($letters) - 1;
  $numbersLength = strlen($numbers) - 1;
  return  substr($letters, rand(0, $lettersLength-1), 1) .
      substr($letters, rand(0, $lettersLength-1), 1) .
      substr($letters, rand(0, $lettersLength-1), 1) .
      substr($numbers, rand(0, $numbersLength), 1) .
      substr($numbers, rand(0, $numbersLength), 1) .
      substr($numbers, rand(0, $numbersLength), 1);
}

function codeNumber($lenght = 4) {
  $numbers = "0123456789";
  $numbersLength = strlen($numbers) - 1;
  
  $code = "";
  for($x=0; $x<$lenght; $x++)
      $code = $code . substr($numbers, rand(0, $numbersLength), 1); 
  return $code;
}

function calculaedad($fechanacimiento){
  list($ano,$mes,$dia) = explode("-",$fechanacimiento);
  $ano_diferencia  = date("Y") - $ano;
  $mes_diferencia = date("m") - $mes;
  $dia_diferencia   = date("d") - $dia;
  if ($dia_diferencia < 0 || $mes_diferencia < 0)
    $ano_diferencia--;
  return $ano_diferencia;
}

function sinTildes($cadena) {
    $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
    $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    $cadena = mb_convert_encoding($cadena, 'UTF-8', 'auto');
    $cadena = strtr($cadena, $originales, $modificadas);
    $cadena = mb_strtolower($cadena, 'UTF-8');
    
    return $cadena;
}

function sinComillas($cadena){
    return str_replace(['"', '\''], "", $cadena);
}

function validarNombre($nombre){
  $names = explode(" ", $nombre);
  if(count($names) < 2){
    return [ 'success' => false, 'mensaje' => 'Ingrese al menos un nombre y un apellido' ];
  }

  list($nombre, $apellido) =  $names;
  if(strlen($nombre) < 2 || strlen($apellido) < 2){
    return [ 'success' => false, 'mensaje' => 'Los nombres y/o apellidos no pueden tener menos de 3 dígitos' ];
  }

  if(is_numeric($nombre) || is_numeric($apellido)){
    return [ 'success' => false, 'mensaje' => 'Los nombres y/o apellidos no pueden ser numericos' ];
  }
  
  return [ 'success' => true, 'mensaje' => 'Nombre valido' ];

}

function ValidarCedula($cedula){
  $suma = 0;
  $longitud = strlen($cedula);
  $ultimoDigito = intval($cedula[$longitud - 1]);

  for ($i = 0; $i < $longitud - 1; $i++) {
      $digito = intval($cedula[$i]);
      if ($i % 2 === 0) { // Posición impar
          $digito *= 2;
          if ($digito > 9) $digito -= 9;
      }
      $suma += $digito;
  }

  $verificador = (10 - ($suma % 10)) % 10;

  return $verificador === $ultimoDigito;
}

function validarDocumentoEcuatoriano($numero) {
  // Eliminar espacios en blanco
  $numero = trim($numero);

  // Verificar que solo contenga números y tenga 10 o 13 dígitos
  if (!preg_match('/^\d{10,13}$/', $numero)) {
      return false;
  }

  $provincia = intval(substr($numero, 0, 2));
  if ($provincia < 1 || $provincia > 24) {
      return false;
  }

  $tipo = strlen($numero); // 10 para cédula, 13 para RUC

  // Validar cédula
  if ($tipo === 10) {
      return ValidarCedula($numero) ? "CEDULA" : false;
  }

  if($tipo === 13){
      // Validar RUC natural (13 dígitos, termina en 001 y tercer dígito entre 0 y 5)
      if (intval($numero[2]) >= 0 && intval($numero[2]) <= 5 && substr($numero, -3) === "001") {
          return ValidarCedula(substr($numero, 0, 10)) ? "RUC_NATURAL" : false;
      }
  
      // Validar RUC jurídico (13 dígitos, tercer dígito es 9 y termina en 001)
      if (intval($numero[2]) === 9 && substr($numero, -3) === "001") {
          return "RUC_JURIDICO";
      }
  
      // Validar RUC público (13 dígitos, tercer dígito es 6 y termina en 001)
      if (intval($numero[2]) === 6 && substr($numero, -3) === "001") {
          return "RUC_PUBLICO";
      }
  }

  return false;
}

function validar_ruc_juridico_ecuador($ruc) {
  // Eliminar espacios en blanco y guiones
  $ruc = preg_replace('/[^0-9]/', '', $ruc);

  // Verificar que el RUC tenga 13 dígitos
  if (strlen($ruc) !== 13) {
      return false;
  }

  // Verificar que los dos primeros dígitos sean 20 (correspondientes a personas jurídicas)
  if ((int)substr($ruc, 0, 2)  > 22 || (int)substr($ruc, 0, 2) <= 0) {
      return false;
  }

  // Verificar que el tercer dígito sea un valor válido (0, 1, 2, 3, 4 o 5)
  $tercer_digito = (int)$ruc[2];
  $suma = 0;
  $pos_digito_verificador = 0;
  if ($tercer_digito == 9) {
      $posicion_digito_verificador = 9;
      $coeficientes = array(4, 3, 2, 7, 6, 5, 4, 3, 2);
      for ($i = 0; $i < 9; $i++) {
          $suma += (int)$ruc[$i] * $coeficientes[$i];
      }
  } else if ($tercer_digito == 6) {
      $coeficientes = array(3, 2, 7, 6, 5, 4, 3, 2);
      $posicion_digito_verificador = 8;
      for ($i = 0; $i < 8; $i++) {
          $suma += (int)$ruc[$i] * $coeficientes[$i];
      }
  } else {
      return false;
  }

  $resto = $suma % 11;

  $digito_verificador = $resto === 0 ? 0 : 11 - $resto;

  if ((int)$ruc[$posicion_digito_verificador] !== $digito_verificador) {
      return false;
  }

  // Verificar que los tres últimos dígitos sean '001' (correspondientes a RUC jurídicos)
  if (substr($ruc, 10, 3) !== '001') {
      return false;
  }

  return true;
}

function editor_encode($texto){
  return htmlentities(htmlspecialchars($texto));
}

function editor_decode($texto){
  return html_entity_decode(htmlspecialchars_decode($texto));
}

function ExecuteRemoteQuery($link){
  $ch = curl_init($link);
  $headers = array();
  $headers[] = 'Content-Type: application/json';
  $headers[] = 'Api-Key: '.api_key;

  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");                                                                     
  curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);   
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
  $response = curl_exec($ch);
  curl_close($ch);
  return json_decode($response);
}

function mylogFile($filename, $texto, $title=""){
    global $request;
    $folder = "logs/".alias;
    
    if (!file_exists($folder)) {
        mkdir($folder, 0777);
    }
    $file = $folder."/".$filename.".log";
    $log = "[".fecha()."] ".$title." ".$texto;
	file_put_contents($file, PHP_EOL . $log, FILE_APPEND);
}

function sumarTiempo($cantidad, $tiempo){
  // EJEMPLOS
  // $cantidad +5, -5, -1
  //$tiempo => hours, minute, second
  date_default_timezone_set('America/Guayaquil');
  $mifecha = new DateTime(); 
  $mifecha->modify($cantidad.' '.$tiempo); 
  return $mifecha->format('Y-m-d H:i:s');
}

function sumarTiempo2($time, $cantidad, $tiempo){
  // EJEMPLOS
  // $cantidad +5, -5, -1
  //$tiempo => hours, minute, second
  date_default_timezone_set('America/Guayaquil');
  $mifecha = new DateTime($time); 
  $mifecha->modify($cantidad.' '.$tiempo); 
  return $mifecha->format('H:i');
}

function sumarTiempoSeguro($hora, $minutos){
    date_default_timezone_set('America/Guayaquil');

    // Fecha ficticia solo para control de cruce de día
    $fechaBase = '2000-01-01';

    $inicio = new DateTime("$fechaBase $hora");
    $fin = clone $inicio;
    $fin->modify("+$minutos minutes");

    // Si cruza al día siguiente, no es válido
    if ($fin->format('Y-m-d') !== $inicio->format('Y-m-d')) {
        return false;
    }

    return $fin->format('H:i');
}


function diffTime($datetime1, $datetime2) {
  $datetime1 = new DateTime($datetime1);
  $datetime2 = new DateTime($datetime2);

  $diff = $datetime1->diff($datetime2);
  $horas = $diff->format("%h");
  $minutos = $diff->format("%i");

  $r = [];
  $r["horas"] = $horas;
  $r["minutos"] = $minutos;
  return $r;
}

function normalizarTelefono($telefono) {
    if (empty($telefono)) {
        return null;
    }
    
    $soloNumeros = preg_replace('/\D/', '', $telefono); // Quitar todo lo que no sea número
    
    if (substr($soloNumeros, 0, 1) === '0') { // Si empieza con 0, quitarlo
        $soloNumeros = substr($soloNumeros, 1);
    }
     
    if (substr($soloNumeros, 0, 3) === '593' && strlen($soloNumeros) === 12) { // Si empieza con 593 y tiene 12 dígitos
        return '+' . $soloNumeros;
    }
    
    if (strlen($soloNumeros) === 9 && substr($soloNumeros, 0, 1) === '9') { // Si tiene 9 dígitos y empieza con 9
        return '+593' . $soloNumeros;
    }

    // No cumple formato
    return null;
}

ob_end_flush();

 

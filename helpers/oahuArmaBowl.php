<?php

//Oahu (cod_empresa 121): los productos "arma tu bowl" ganan una unidad extra
//en una opcion segun el dia: martes = topping, jueves = fruta
function aplicarReglaArmaBowlOahu($Clproductos, $producto){
	if(!$Clproductos->getArmaBowl($producto['cod_producto'])){
		return $producto;
	}

	$day = dayOfWeek(fecha()); //2 Martes - 4 Jueves
	if($day == 5){
		$producto['opciones'] = addMaxItemToOptionOahu('fruta', $producto['opciones']);
	}else if($day == 2){
		$producto['opciones'] = addMaxItemToOptionOahu('topping', $producto['opciones']);
	}

	return $producto;
}

function addMaxItemToOptionOahu($findToTitleOption, $opciones){
	foreach($opciones as $key => $opcion){
		$pos = strpos(strtolower($opcion['titulo']), $findToTitleOption);
		if($pos !== false){
			$opciones[$key]['cantidad'] = $opcion['cantidad'] + 1;
			$opciones[$key]['descripcion'] = ($findToTitleOption == 'fruta') ? "🍇🥭 1 Fruta gratis por Juefru" : "🥥 1 Topping gratis por Marto";
		}
	}

	return $opciones;
}

<?php

require_once 'inc/bt04a.php';
require_once 'inc/esp8266.php';
require_once 'inc/easyesp8266.php';

function source_getstate($a_source) {
	switch ($a_source['type']) {
		case 'BT-04A':
			return bt04a_getstate($a_source['type_params']);
		case 'ESP8266':
			return false;
		case 'EASYESP8266':
			return false;
	}
	return false;
}

function source_on($a_source) {
	switch ($a_source['type']) {
		case 'BT-04A':
			return bt04a_on($a_source['type_params']);
		case 'ESP8266':
			return esp8266_switch_on($a_source['type_params']);
		case 'EASYESP8266':
			return easyesp8266_switch_on($a_source['type_params']);
	}
	return false;
}

function source_off($a_source) {
	switch ($a_source['type']) {
		case 'BT-04A':
			return bt04a_off($a_source['type_params']);
		case 'ESP8266':
			return esp8266_switch_off($a_source['type_params']);
		case 'EASYESP8266':
			return easyesp8266_switch_off($a_source['type_params']);
	}
	return false;
}

function source_init($a_source) {
	switch ($a_source['type']) {
		case 'BT-04A':
			return true;
		case 'ESP8266':
			return esp8266_init($a_source['type_params']);
		case 'EASYESP8266':
			return true;
	}
	return false;
}

function source_close() {
	esp8266_close();

	return true;
}

/**
 * @param string $a_name
 * @return unknown|boolean
 */
function & source_getbyname($a_name) {
	foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
		if ( $GLOBALS ['heating'] ['sources'] [$l_idx]['name'] == $a_name ) {
			return $GLOBALS ['heating'] ['sources'] [$l_idx];
		}
	}
	return false;

}
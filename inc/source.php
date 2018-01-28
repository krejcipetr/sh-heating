<?php

require_once 'inc/bt04a.php';
require_once 'inc/esp8266.php';

function source_getstate($a_source) {
	switch ($a_source['type']) {
		case 'BT-04A':
			return bt04a_getstate($a_source['type_params']);
		case 'ESP8266':
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
	}
	return false;
}

function source_off($a_source) {
	switch ($a_source['type']) {
		case 'BT-04A':
			return bt04a_off($a_source['type_params']);
		case 'ESP8266':
			return esp8266_switch_off($a_source['type_params']);
	}
	return false;
}

function source_init($a_source) {
	switch ($a_source['type']) {
		case 'BT-04A':
			return true;
		case 'ESP8266':
			return esp8266_init($a_source['type_params']);
	}
	return false;
}

function source_close() {
	esp8266_close();

	return true;
}
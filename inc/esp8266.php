<?php

function esp8266_init ( $a_index ) {
	if ( is_resource ( $GLOBALS ['esp_servers'] [$a_index] [0] ) ) {
		return;
	}
	fprintf ( STDERR, "Oteviram listener\n" );
	
	$GLOBALS ['esp_servers'] [$a_index] [0] = stream_socket_server ( "tcp://0.0.0.0:550" . $a_index, $errno, $errstr );
	
	if ( ! is_resource ( $GLOBALS ['esp_servers'] [$a_index] [0] ) ) {
		throw new Exception ( "$errstr ($errno)" );
	}
}

function esp8266_wait ( $a_index ) {
	if ( is_resource ( $GLOBALS ['esp_servers'] [$a_index] [1] ) ) {
		return;
	}
	
	if ( ! is_resource ( $GLOBALS ['esp_servers'] [$a_index] [0] ) ) {
		esp8266_init ( $a_index );
	}
	
	while ( $l_zmena > 0 || ! is_resource($GLOBALS ['esp_servers'] [$a_index] [1])) {
		$l_r = $GLOBALS ['esp_servers'] [$a_index];
		
		$l_zmen = stream_select ( $l_r, $_w = NULL, $_e = NULL, 1 );
		
		for ( $i = 0; $i < $l_zmen; ++ $i ) {
			if ( $l_r [$i] === $GLOBALS ['esp_servers'] [$a_index] [0] ) {
				
				if ( is_resource ( $GLOBALS ['esp_servers'] [$a_index] [1] ) ) {
					fclose ( $GLOBALS ['esp_servers'] [$a_index] [1] );
				}
				$GLOBALS ['esp_servers'] [$a_index] [1] = stream_socket_accept ( $l_r [$i] );
				if ( ! is_resource ( $GLOBALS ['esp_servers'] [$a_index] [1] ) ) {
					throw new Exception ( socket_last_error ( $l_r [$i] ) );
				}
				stream_set_blocking ( $GLOBALS ['esp_servers'] [$a_index] [1], false );
			}
			else {
				fread ( $l_r [$i], 1000 );
			}
		}
	}
}

function esp8266_switch_on ( $a_index ) {
	esp8266_wait ( $a_index );
	
	while ( 4 !== ($l_ret = fwrite ( $GLOBALS ['esp_servers'] [$a_index] [1], base64_decode ( "oAEBog==" ) )) ) {
		
		fprintf ( STDERR, "Nepodarilo se poslat data %s", var_export ( $GLOBALS ['esp_servers'], true ) );
		
		esp8266_wait ( $a_index );
	}
}

function esp8266_switch_off ( $a_index ) {
	esp8266_wait ( $a_index );
	
	if ( 4 !== fwrite ( $GLOBALS ['esp_servers'] [$a_index], base64_decode ( "oAEAoQ==" ) ) ) {
		fprintf ( STDERR, "Nepodarilo se poslat data" );
	}
}

function esp8266_close () {
	foreach ( $GLOBALS ['esp_servers'] as $l_socket ) {
		fclose ( $l_socket );
	}
}
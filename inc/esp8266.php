<?php

function esp8266_init ( $a_index ) {
	if ( is_resource ( $GLOBALS ['esp_servers'] [$a_index] [0] ) ) {
		return;
	}
	$GLOBALS ['esp_servers'] [$a_index] [0] = stream_socket_server ( "tcp://0.0.0.0:550" . $a_index, $errno, $errstr );

	if ( ! is_resource ( $GLOBALS ['esp_servers'] [$a_index] [0] ) ) {
		throw new Exception ( "$errstr ($errno)" );
	}
}

function esp8266_wait ( $a_index ) {
	if ( ! is_resource ( $GLOBALS ['esp_servers'] [$a_index] [0] ) ) {
		esp8266_init ( $a_index );
	}

	$l_pocetzmen = 1;

	while ( $l_pocetzmen > 0 ) {

		$l_readedsockets = $GLOBALS ['esp_servers'] [$a_index];
		$l_pocetzmen = stream_select ( $l_readedsockets, $_w = NULL, $_e = NULL, 1 );

		for ( $i = 0; $i < $l_pocetzmen; ++ $i ) {

			if ( $l_readedsockets [$i] === $GLOBALS ['esp_servers'] [$a_index] [0] ) {
				if ( is_resource ( $GLOBALS ['esp_servers'] [$a_index] [1] ) ) {
					fclose ( $GLOBALS ['esp_servers'] [$a_index] [1] );
				}
				$GLOBALS ['esp_servers'] [$a_index] [1] = stream_socket_accept ( $l_readedsockets [$i] );
				if ( ! is_resource ( $GLOBALS ['esp_servers'] [$a_index] [1] ) ) {
					throw new Exception ( socket_last_error ( $l_readedsockets [$i] ) );
				}
				stream_set_blocking ( $GLOBALS ['esp_servers'] [$a_index] [1], false );
			}
			else {
				stream_set_blocking ( $l_readedsockets [$i], false );
				fread ( $l_readedsockets [$i], 1000 );
			}
		}
	}
}

function esp8266_switch_on ( $a_index ) {
	esp8266_wait ( $a_index );

	while ( 4 !== fwrite ( $GLOBALS ['esp_servers'] [$a_index] [1], base64_decode ( "oAEBog==" ) ) ) {
		esp8266_wait ( $a_index );
	}
}

function esp8266_switch_off ( $a_index ) {
	esp8266_wait ( $a_index );

	while ( 4 !== fwrite ( $GLOBALS ['esp_servers'] [$a_index] [1], base64_decode ( "oAEAoQ==" ) ) ) {
		esp8266_wait ( $a_index );
	}
}

function esp8266_close () {
	foreach ( $GLOBALS ['esp_servers'] as $l_socket ) {
		foreach ( $l_socket as $l_s ) {
			fclose ( $l_s );
		}
	}
}
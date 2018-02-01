<?php

function esp8266_init ( $a_index ) {
	$GLOBALS ['esp_servers'] [$a_index] = stream_socket_server ( "tcp://0.0.0.0:550" . $a_index, $errno, $errstr );
	if ( ! $GLOBALS ['esp_servers'] [$a_index] ) {
		throw new Exception ( "$errstr ($errno)" );
	}
	$GLOBALS ['esp_servers_conn'] [$a_index] = stream_socket_accept ( $GLOBALS ['esp_servers'] [$a_index] );
	stream_set_blocking ( $GLOBALS ['esp_servers_conn'] [$a_index], false );
}

function esp8266_switch_on ( $a_index ) {
	fwrite ( $GLOBALS ['esp_servers_conn'] [$a_index], base64_decode ( "oAEBog==" ) );
	fread ( $GLOBALS ['esp_servers_conn'] [$a_index], 100 );
}

function esp8266_switch_off ( $a_index ) {
	fwrite ( $GLOBALS ['esp_servers_conn'] [$a_index], base64_decode ( "oAEAoQ==" ) );
	fread ( $GLOBALS ['esp_servers_conn'] [$a_index], 100 );
}

function esp8266_close () {
	foreach ( $GLOBALS ['esp_servers_conn'] as $l_socket ) {
		fclose ( $l_socket );
	}
	
	foreach ( $GLOBALS ['esp_servers'] as $l_socket ) {
		fclose ( $l_socket );
	}
}
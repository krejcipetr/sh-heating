<?php
require_once 'sem.php';

function bridgemaster_connect () {
	$GLOBALS ['bridge'] ['client'] = new Mosquitto\Client ( $GLOBALS ['bridge'] ['id'] );
	$GLOBALS ['bridge'] ['client']->onMessage ( 'bridgemaster_message' );
	$GLOBALS ['bridge'] ['client']->connect ( $GLOBALS ['bridge'] ['host'], $GLOBALS ['bridge'] ['port'], 86400 );

	$GLOBALS ['bridge'] ['client']->subscribe ( 'ready/#', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( 'radiator_actual/#', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( 'source_actual/#', 1 );

	bridge_publish ( "masterready", "" );
}

function bridgeclient_connect () {
	$GLOBALS ['bridge'] ['client'] = new Mosquitto\Client ( $GLOBALS ['bridge'] ['id'] );
	$GLOBALS ['bridge'] ['client']->onMessage ( 'bridgeclient_message' );
	$GLOBALS ['bridge'] ['client']->connect ( $GLOBALS ['bridge'] ['host'], $GLOBALS ['bridge'] ['port'], 86400 );

	$GLOBALS ['bridge'] ['client']->subscribe ( 'config/' . $GLOBALS ['bridge'] ['id'], 1 );

	$GLOBALS ['bridge'] ['client']->subscribe ( 'stop', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( 'synchro', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( 'masterready', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( 'now', 1 );

	bridge_publish ( "ready/" . $GLOBALS ['bridge'] ['id'], "" );
}

/**
 * Dummy function
 */
function bridge_save () {
}

function bridge_load ( $a_configfile ) {
	$l_dir = dirname ( $a_configfile );
	if ( empty ( $l_dir ) || $l_dir == '.') {
		$l_dir = $GLOBALS ['logs'] ;
	}

	$GLOBALS ['bridge'] = json_decode ( file_get_contents($l_dir . "/" . $a_configfile), true );
}

/**
 *
 * @param stdClass $message
 */
function bridgemaster_message ( $message ) {
	$l_config = json_decode ( $message->payload, true );
	$l_casti = explode ( "/", $message->topic );
	switch ( $l_casti [0] ) {
		case 'radiator_actual' :
			fprintf ( STDOUT, "MQTT: Actual state of radiator [%s]" . PHP_EOL, $l_casti [1] );

			radiators_load ();

			$l_radiator = & radiator_getbyname ( $l_casti [1] );
			if ( $l_radiator === false ) {
				fprintf ( STDERR, "Nenasel se radiator" );
				semup ();
				return;
			}

			$l_radiator ['current'] = $l_config ['current'];

			if ( $l_radiator ['conf'] != 'modified' ) {
				$l_radiator ['required'] = $l_config ['required'];
				$l_radiator ['lastdata'] = $l_config ['lastdata'];
				$l_radiator ['conf'] = $l_config ['conf'];
			}

			radiators_save ();

			break;

		case 'source_actual' :
			if ( empty ( $l_casti [1] ) )
				break;

			fprintf ( STDOUT, "MQTT: Actual state of source [%s]" . PHP_EOL, $l_casti [1] );

			radiators_load ();

			$l_source = & source_getbyname ( $l_casti [1] );
			if ( $l_source === false ) {
				fprintf ( STDERR, "Nenasel se source" . PHP_EOL );
				semup ();
				return;
			}

			$l_source ['state'] = ( bool ) $l_config;

			radiators_save ();

			break;

		case 'ready' :
			fprintf ( STDOUT, "MQTT: Welcome [%s]" . PHP_EOL, $l_casti [1] );

			radiators_load ();
			bridge_sendconfiguration ( $l_casti [1] );
			radiators_save ();

			bridge_publish ( 'synchro', $GLOBALS ['heating'] ['next'] );

			break;

		case  'up':
			fprintf ( STDOUT, "MQTT: Request for heating radiator [%s]" . PHP_EOL, $l_casti [1] );

			radiators_load ();

			$l_radiator = & radiator_getbyname ( $l_casti [1] );
			if ( $l_radiator === false ) {
				fprintf ( STDERR, "Nenasel se radiator" );
				semup ();
				return;
			}

			$l_radiator ['current'] = $l_radiator ['comfort'];
			$l_radiator ['conf']  = 'modified';

			radiators_save ();

			break;

		case  'down':
			fprintf ( STDOUT, "MQTT: Request for heating radiator [%s]" . PHP_EOL, $l_casti [1] );

			radiators_load ();

			$l_radiator = & radiator_getbyname ( $l_casti [1] );
			if ( $l_radiator === false ) {
				fprintf ( STDERR, "Nenasel se radiator" );
				semup ();
				return;
			}

			$l_radiator ['current'] = $l_radiator ['night'];
			$l_radiator ['conf']  = 'modified';

			radiators_save ();

			break;


	}
}

/**
 *
 * @param stdClass $message
 */
function bridgeclient_message ( $message ) {
	$l_config = json_decode ( $message->payload, true );
	$l_casti = explode ( "/", $message->topic );
	switch ( $l_casti [0] ) {
		case 'masterready' :
			fprintf ( STDOUT, "MQTT: New master ready" . PHP_EOL );
			bridge_publish ( "ready/" . $GLOBALS ['bridge'] ['id'], "" );
			break;

		case 'config' :
			fprintf ( STDOUT, "MQTT: Got configuration ... " );

			$GLOBALS ['heating'] ['radiators'] = $l_config ['radiators'];
			$GLOBALS ['heating'] ['sources'] = $l_config ['sources'];

			foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
				unset ( $l_source );
				$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];

				$GLOBALS ['bridge'] ['client']->subscribe ( 'source_set/' . $l_source ['name'], 1 );

				fprintf ( STDOUT, "Initialize source [%s]" . PHP_EOL, $l_source ['name'] );
				source_init ( $l_source );
				$l_state = source_getstate ( $l_source );
				bridge_publish ( 'source_actual/' . $l_source ['name'], $l_state );
			}

			foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
				unset ( $l_radiator );
				$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

				$GLOBALS ['bridge'] ['client']->subscribe ( 'radiator_reconfigure/' . $l_radiator ['name'], 1 );
			}

			fprintf ( STDOUT, "Done" . PHP_EOL );
			break;

		case 'radiator_reconfigure' :
			fprintf ( STDOUT, "MQTT: Reconfiguration of radiator [%s] ...", $l_casti [1] );

			$l_radiator = & radiator_getbyname ( $l_casti [1] );
			if ( $l_radiator === false ) {
				fprintf ( STDERR, "Nenasel se radiator" . PHP_EOL );
				return;
			}
			else {
				$l_radiator = $l_config;
			}

			// Nova konfigurace v JSON, ale neni v hlavicich
			if ( ! cometblue_sendconf ( $l_config, PIN ) ) {
				echo "Error", PHP_EOL;
				return;
			}
			echo "OK", PHP_EOL;
			$l_radiator ['conf'] = 'saved';

			break;

		case 'source_set' :
			fprintf ( STDOUT, "MQTT: Set source [%s] to %s" . PHP_EOL, $l_casti [1], $l_config );
			$l_source = & source_getbyname ( $l_casti [1] );
			if ( $l_source === false ) {
				fprintf ( STDERR, "Nenasel se source" . PHP_EOL );
				return;
			}

			$l_source ['state'] = ( bool ) $l_config;

			// Spusteni/ vypnuti kotle
			if ( $l_source ['state'] ) {
				source_on ( $l_source );
			}
			else {
				source_off ( $l_source );
			}
			break;

		case 'synchro' :

			$l_config = strtotime ( $l_config );

			fprintf ( STDOUT, "MQTT: New time of synchronization to %s" . PHP_EOL, strftime ( "%x %X", $l_config ) );

			$l_synchro = $l_config - 60 - 20 * count ( $GLOBALS ['heating'] ['radiators'] );

			if ( $l_synchro > time () ) {
				// fprintf ( STDOUT, "Set to %s" . PHP_EOL, strftime("%X",$l_synchro ));
				$GLOBALS ['synchro'] = $l_synchro;
			}
			else {
				fprintf ( STDERR, "Old synch time %s\nNot set." . PHP_EOL, strftime ( "%X", $l_synchro ) );
			}
			break;

		case 'now' :
			fprintf ( STDOUT, "MQTT: Request for communication" . PHP_EOL );

			$GLOBALS ['now'] = 1;

			break;
	}
}

function bridge_disconnect () {
	$GLOBALS ['bridge'] ['client']->unsubscribe ( '#' );
}

function bridge_sendconfiguration ( $a_bridgeid ) {
	$l_configuration = array ('radiators' => array (), "sources" => array () );

	foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

		if ( $l_radiator ['bridge'] != $a_bridgeid ) {
			continue;
		}
		$l_configuration ['radiators'] [$l_idx] = $l_radiator;
	}
	foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
		unset ( $l_source );
		$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];

		if ( $l_source ['bridge'] != $a_bridgeid ) {
			continue;
		}
		$l_configuration ['sources'] [$l_idx] = $l_source;
	}

	if ( $l_configuration ['radiators'] || $l_configuration ['sources'] ) {
		bridge_publish ( 'config/' . $a_bridgeid, $l_configuration );
	}
}

function bridge_publish ( $a_func, $a_payload ) {
	$GLOBALS ['bridge'] ['client']->publish ( $a_func, json_encode ( $a_payload ) );
}

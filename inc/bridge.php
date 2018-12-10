<?php
require_once 'sem.php';

function bridgemaster_connect () {
	$GLOBALS ['bridge'] ['client'] = new Mosquitto\Client ();
	$GLOBALS ['bridge'] ['client']->onMessage ( 'bridge_message' );
	$GLOBALS ['bridge'] ['client']->setWill($GLOBALS ['bridge'] ['id'].'/stop', "", 1, 0);
	$GLOBALS ['bridge'] ['client']->connect ( $GLOBALS ['bridge'] ['host'], $GLOBALS ['bridge'] ['port'], 300 );

	$GLOBALS ['bridge'] ['client']->subscribe ( '+/ready', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( '+/radiator_actual/#', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( '+/source_actual/#', 1 );
}

function bridgeclient_connect () {
	$GLOBALS ['bridge'] ['client'] = new Mosquitto\Client ();
	$GLOBALS ['bridge'] ['client']->onMessage ( 'bridge_message' );
	$GLOBALS ['bridge'] ['client']->connect ( $GLOBALS ['bridge'] ['host'], $GLOBALS ['bridge'] ['port'], 300 );

	$GLOBALS ['bridge'] ['client']->subscribe ( $GLOBALS ['bridge'] ['id'] . '/config', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe (  '+/stop', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( $GLOBALS ['bridge'] ['id'] . '/source_set/#', 1 );
	$GLOBALS ['bridge'] ['client']->subscribe ( $GLOBALS ['bridge'] ['id'] . '/radiator_reconfigure/#', 1 );

	$GLOBALS ['bridge'] ['client']->publish ( $GLOBALS ['bridge'] ['id'] . "/ready", "", 2, 1 );
}

/**
 * Dummy function
 */
function bridge_save () {
}

function bridge_load ( $a_configfile ) {
	$GLOBALS ['bridge'] = json_decode ( file_get_contents ( $GLOBALS ['logs'] . $a_configfile ), true );
}

function bridge_message ( $message ) {
	$l_config = json_decode ( $message->payload, true );
	$l_casti = explode ( "/", $message->topic );
	switch ( $l_casti [1] ) {
		case 'config' :
			fprintf ( STDOUT, "MQTT: Got configuration ... "  );

			$GLOBALS ['heating'] ['radiators'] = $l_config ['radiators'];
			$GLOBALS ['heating'] ['sources'] = $l_config ['sources'];

			foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
				unset ( $l_source );
				$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];

				fprintf ( STDOUT, "Initialize source [%s]" . PHP_EOL, $l_source ['name'] );
				source_init ( $l_source );

				$l_json = source_getstate($l_source);
				$GLOBALS ['bridge'] ['client']->publish ( $GLOBALS ['bridge'] ['id'] . '/source_actual/' . $l_source ['name'] , $l_json );

			}
			fprintf ( STDOUT, "Done" . PHP_EOL );
			break;

		case 'radiator_actual' :
			fprintf ( STDOUT, "MQTT: Actual state of radiator [%s]" . PHP_EOL, $l_casti [2] );

			radiators_load();

			$l_radiator = & radiator_getbyname ( $l_casti [2] );
			if ( $l_radiator === false ) {
				fprintf ( STDERR, "Nenasel se radiator" );
				semup();
				return;
			}

			$l_radiator ['required'] = $l_config ['required'];
			$l_radiator ['current'] = $l_config ['current'];
			$l_radiator ['lastdata'] = $l_config ['lastdata'];
			$l_radiator ['conf'] = $l_config ['conf'];

			radiators_save();

			break;

		case 'radiator_reconfigure' :
			fprintf ( STDOUT, "MQTT: Reconfiguration of radiator [%s] ..." , $l_casti [2] );

			$l_radiator = & radiator_getbyname ( $l_casti [2] );
			if ( $l_radiator === false ) {
				fprintf ( STDERR, "Nenasel se radiator" );
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

		case 'source_actual' :
			fprintf ( STDOUT, "MQTT: Actual state of source [%s]" . PHP_EOL, $l_casti [2] );

			radiators_load();

			$l_source = & source_getbyname ( $l_casti [2] );
			if ( $l_source === false ) {
				fprintf ( STDERR, "Nenasel se source" );
				semup();
				return;
			}

			$l_source ['state'] = ( bool ) $l_config;

			radiators_save();

			break;


		case 'source_set' :
			fprintf ( STDOUT, "MQTT: Set source [%s] to %s" . PHP_EOL, $l_casti [2], $l_config);
			$l_source = & source_getbyname ( $l_casti [2] );
			if ( $l_source === false ) {
				fprintf ( STDERR, "Nenasel se source" );
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

		case 'stop' :
			fprintf ( STDOUT, "MQTT: Got stop request [%s]" . PHP_EOL, $l_casti [0]);
			$GLOBALS ['stop'] = true;
			break;

		case 'ready' :
			fprintf ( STDOUT, "MQTT: Welcome [%s]" . PHP_EOL, $l_casti [0]);

			radiators_load();
			bridge_sendconfiguration ( $l_casti [0] );
			radiators_save();

			break;
	}
}

function bridge_disconnect () {
	$GLOBALS ['bridge'] ['client']->unsubscribe('#');
}

function bridge_logger () {
	var_dump ( func_get_args () );
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
		$GLOBALS ['bridge'] ['client']->publish ( $a_bridgeid . '/config', json_encode ( $l_configuration ) );
	}
}

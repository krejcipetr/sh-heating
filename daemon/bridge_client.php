<?php
chdir ( dirname ( __FILE__ ) . '/..' );

require_once 'config.php';

require_once 'inc/bridge.php';
require_once 'inc/radiator.php';
require_once 'inc/cometblue.php';
require_once 'inc/source.php';

if ( ! $argv [1] ) {
	$l_configfile = 'bridge.json';
}
else {
	$l_configfile = $argv [1];
}

bridge_load ( $l_configfile );

bridgeclient_connect ();

$GLOBALS ['stop'] = false;

$GLOBALS ['synchro'] = INTERVAL * 60 + time ();

while ( ! $GLOBALS ['stop'] ) {

	$GLOBALS ['now'] = 0;
	printf ( "Processing MQTT to %s" . PHP_EOL, strftime ( "%X", $GLOBALS ['synchro'] ) );
	while ( ! $GLOBALS ['now'] && ! $GLOBALS ['stop'] && time () < $GLOBALS ['synchro'] ) {
		try {
			$GLOBALS ['bridge'] ['client']->loop ();
		} catch ( Mosquitto\Exception $m ) {
			bridgeclient_connect ();
		}

		sleep ( 1 );
	}

	if ( $GLOBALS ['stop'] ) {
		continue;
	}

	if ( ! $GLOBALS ['now'] ) {
		$GLOBALS ['synchro'] = INTERVAL * 60 + time ();
	}

	printf ( "\n===============  %s  ================= \n", strftime ( "%X" ) );

	fprintf ( STDOUT, "Reading radiators" . PHP_EOL );

	// Nacteni udaju
	// Test, zda-li se maji aktualizovat nastaveni v hlavicich
	foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = &$GLOBALS ['heating'] ['radiators'] [$l_idx];

		echo $l_radiator ['mac'], '=', $l_radiator ['name'], "...";

		unset ( $l_radiator_now );
		$l_radiator_now = cometblue_receiveconf ( $l_radiator ['mac'], PIN );
		if ( $l_radiator_now === false ) {
			echo "Error", PHP_EOL;
			continue;
		}
		echo "OK", PHP_EOL;

		$l_radiator ['required'] = $l_radiator_now ['required'];
		$l_radiator ['current'] = $l_radiator_now ['current'];
		$l_radiator ['lastdata'] = $l_radiator_now ['lastdata'];

		// Kontrola, zdali se nemenily pozadovanee nastaveni, tj. programovani, pozaovana teplota max conforty, cas hlavice v rozmezi 1min
		// Pokud nesedi, tak hlavici prenastav
		$l_correct = false;
		if ( $l_radiator ['required'] > $l_radiator ['comfort'] ) {
			echo "BAD value:", 'required', PHP_EOL;
			$l_radiator ['required'] = $l_radiator ['comfort'];
			$l_correct = true;
		}
		foreach ( array ('comfort', 'night', 'offset', 'pondeli', 'utery', 'streda', 'ctvrtek', 'patek', 'sobota', 'nedele', 'dovolena' ) as $l_colname ) {
			if ( $l_radiator [$l_colname] != $l_radiator_now [$l_colname] ) {
				echo "BAD value:" , $l_colname, PHP_EOL;
				$l_correct = true;
				break;
			}
		}
		// Odesli informace na MQTT
		echo "Sending data ", $l_radiator ['name'];
		bridge_publish ( 'radiator_actual/' . $l_radiator ['name'], $l_radiator );
		echo " OK", PHP_EOL;

		if ( $l_correct ) {
			echo "Repairing ... ";
			cometblue_sendconf ( $l_radiator, PIN );
			echo "OK", PHP_EOL;
		}
	}
}

bridge_disconnect ();

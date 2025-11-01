<?php
chdir ( dirname ( __FILE__ ) . '/..' );

require_once 'config.php';

require_once 'inc/bridge.php';
require_once 'inc/radiator.php';
require_once 'inc/cometblue.php';
require_once 'inc/source.php';

if ( empty( $GLOBALS[ 'argv' ] [ 1 ] ) ) {
	$l_configfile = 'bridge.json';
}
else {
	$l_configfile = $argv [ 1 ];
}

bridge_load ( $l_configfile );

bridgeclient_connect ();

$GLOBALS [ 'stop' ] = false;

$GLOBALS [ 'synchro' ] = INTERVAL * 60 + time ();

while ( !$GLOBALS [ 'stop' ] ) {

	$GLOBALS [ 'now' ] = 0;
	printf ( "Processing MQTT to %s" . PHP_EOL, strftime ( "%X", $GLOBALS [ 'synchro' ] ) );
	while ( !$GLOBALS [ 'now' ] && !$GLOBALS [ 'stop' ] && time () < $GLOBALS [ 'synchro' ] ) {
		try {
			$GLOBALS [ 'bridge' ] [ 'client' ]->loop ();
		}
		catch ( Mosquitto\Exception $m ) {
			bridgeclient_connect ();
		}

		sleep ( 1 );
	}

	if ( $GLOBALS [ 'stop' ] ) {
		continue;
	}

	if ( !$GLOBALS [ 'now' ] ) {
		$GLOBALS [ 'synchro' ] = INTERVAL * 60 + time ();
	}

	printf ( "\n===============  %s  ================= \n", strftime ( "%X" ) );

	fprintf ( STDOUT, "Reading radiators" . PHP_EOL );

	// Nacteni udaju
	// Test, zda-li se maji aktualizovat nastaveni v hlavicich
	foreach ( array_keys ( $GLOBALS [ 'heating' ] [ 'radiators' ] ) as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = &$GLOBALS [ 'heating' ] [ 'radiators' ] [ $l_idx ];

		echo $l_radiator [ 'mac' ], '=', $l_radiator [ 'name' ], "...";

		unset ( $l_radiator_now );
		$l_radiator_now = cometblue_receiveconf ( $l_radiator [ 'mac' ], PIN );
		if ( $l_radiator_now === false ) {
			echo "Error", PHP_EOL;
			continue;
		}
		echo "OK", PHP_EOL;

		if ( testing ) {
			var_export ( $l_radiator_now );
		}

		radiator_clean ( $l_radiator_now );

		$l_bad = false;
		foreach ( array('comfort', 'night', 'offset', 'pondeli', 'utery', 'streda', 'ctvrtek', 'patek', 'sobota', 'nedele', 'dovolena') as $l_colname ) {
			if ( $l_radiator [ $l_colname ] != $l_radiator_now [ $l_colname ] ) {
				echo "BAD value:", $l_colname, PHP_EOL;
				var_export ( $l_radiator [ $l_colname ] );
				var_export ( $l_radiator_now [ $l_colname ] );
				$l_bad = true;
			}
		}

		// Prevezmi hodnoty pouze pokud to bylo nastaveni planovace korektni, jinak oprav
		if ( !$l_bad ) {
			$l_radiator [ 'required' ] = $l_radiator_now [ 'required' ];
			$l_radiator [ 'current' ] = $l_radiator_now [ 'current' ];
			$l_radiator [ 'lastdata' ] = $l_radiator_now [ 'lastdata' ];

			// Kontrola, zdali se nemenily pozadovanee nastaveni, tj. programovani, pozaovana teplota max comforty, cas hlavice v rozmezi 1min
			// Pokud nesedi, tak hlavici prenastav
			if ( $l_radiator [ 'required' ] > $l_radiator [ 'comfort' ] ) {
				echo "BAD value:", 'required', PHP_EOL;
				$l_radiator [ 'required' ] = $l_radiator [ 'comfort' ];
				$l_bad = true;
			}
		}
		else {
			// Urci required podle definice
			$l_defindex = ['pondeli', 'utery', 'streda', 'ctvrtek', 'patek', 'sobota', 'nedele'] [ intval ( date ( "w" ) ) ];
			$l_daydef = $l_radiator[ $l_defindex ];
			// Je cas v nejakem intervalu?
			$l_cas = strftime ( "%H:%M" );
			$l_platne = array_filter ( $l_daydef, function ( $l_daydef ) use ( $l_cas ) {
				return $l_daydef[ 'from' ] <= $l_cas && $l_cas <= $l_daydef[ 'to' ];
			} );
			$l_requiredtemp = count ( $l_platne ) > 1 ? $l_radiator[ 'comfort' ] : $l_radiator[ 'night' ];
			echo "Setting required to ", $l_requiredtemp, PHP_EOL;
			$l_radiator [ 'required' ] = $l_requiredtemp;
		}

		// Odesli informace na MQTT
		echo "Sending data ", $l_radiator [ 'name' ];
		bridge_publish ( 'radiator_actual/' . $l_radiator [ 'name' ], $l_radiator );
		echo " OK", PHP_EOL;

		if ( $l_bad ) {
			echo "Repairing ... ";
			cometblue_sendconf ( $l_radiator, PIN );
			echo "OK", PHP_EOL;
		}

		sleep ( 10 );
	}
}

bridge_disconnect ();

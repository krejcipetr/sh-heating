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

$GLOBALS['synchro']  = INTERVAL * 60 + time ();

while ( ! $GLOBALS ['stop'] ) {

    $GLOBALS ['now']  = 0;
	printf ( "Processing MQTT to %s".PHP_EOL, strftime ( "%X", $GLOBALS['synchro'] ) );
	while ( ! $GLOBALS ['now'] &&  ! $GLOBALS ['stop'] &&  time () < $GLOBALS['synchro'] ) {
		$GLOBALS ['bridge'] ['client']->loop ();
		sleep ( 1 );
	}

	if ( $GLOBALS ['stop'] ) {
		continue;
	}

	if (! $GLOBALS ['now'] ) {
        $GLOBALS['synchro']  = INTERVAL * 60 + time ();
    }
	
	printf ( "\n===============  %s  ================= \n", strftime ( "%X" ) );

	fprintf(STDOUT, "Reading radiators".PHP_EOL);

	// Nacteni udaju
	// Test, zda-li se maji aktualizovat nastaveni v hlavicich
	foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

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

		// Odesli informace na MQTT
		bridge_publish( 'radiator_actual/' . $l_radiator ['name'] , $l_radiator );
	
		echo "OK", PHP_EOL;
	}
}

bridge_disconnect ();

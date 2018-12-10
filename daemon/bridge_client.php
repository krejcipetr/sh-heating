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

while ( ! $GLOBALS ['stop'] ) {

    $l_minutes = INTERVAL * 60 + time ();
	printf ( "Processing MQTT to %s".PHP_EOL, strftime ( "%X", $l_minutes ) );
	while ( time () < $l_minutes ) {
		$GLOBALS ['bridge'] ['client']->loop ();
		sleep ( 1 );
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
		$l_json = json_encode ( $l_radiator );
		$GLOBALS ['bridge'] ['client']->publish ( $GLOBALS ['bridge'] ['id'] . '/radiator_actual/' . $l_radiator ['name'] , $l_json );
	}
}

bridge_disconnect ();

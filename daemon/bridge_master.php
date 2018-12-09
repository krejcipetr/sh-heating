<?php
chdir ( dirname ( __FILE__ ) . '/..' );

require_once 'config.php';
require_once 'inc/bridge.php';
require_once 'inc/radiator.php';
require_once 'inc/source.php';

radiators_load ();

if (! $argv[1]) {
	$l_configfile = 'bridge.json';
}
else {
	$l_configfile = $argv[1];
}

bridge_load ($l_configfile);

// Pripojuji se na vse
bridgemaster_connect ( );

$GLOBALS ['bridge'] ['client']->loopForever (300);

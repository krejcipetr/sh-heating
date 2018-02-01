<?php
chdir ( dirname ( __FILE__ ) );

require_once 'config.php';
require_once 'inc/source.php';
require_once 'inc/radiator.php';

radiators_load();

foreach ( $GLOBALS ['heating'] ['sources'] as $l_source ) {
	source_init ( $l_source );
}

foreach ( $GLOBALS ['heating'] ['sources'] as $l_source ) {
	source_off ( $l_source );
}
sleep ( 20 );
foreach ( $GLOBALS ['heating'] ['sources'] as $l_source ) {
	printf ( "%s=%s" . PHP_EOL, $l_source ['name'], (source_getstate ( $l_source )) ? "On" : "Off" );
}

semup();
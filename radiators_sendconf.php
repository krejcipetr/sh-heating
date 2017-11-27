<?php
chdir ( dirname ( __FILE__ ) );

require_once 'cometblue.php';
require_once 'inc/radiator.php';
require_once 'inc/config.php';

radiators_load ();

foreach ( $GLOBALS ['heating']['radiators'] as $l_idx => $l_radiator ) {
	printf ( "%s=%s\n", $l_radiator ['mac'], $l_radiator ['name'] );
	
	cometblue_sendconf ( $l_radiator, PIN );
}

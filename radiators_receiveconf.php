<?php
chdir ( dirname ( __FILE__ ) );

require_once 'config.php';
require_once 'inc/radiator.php';
require_once 'inc/cometblue.php';

radiators_load();

foreach ( $GLOBALS ['heating']['radiators'] as $l_idx=>$l_radiator ) {
  
  echo $l_radiator ['mac'], '=', $l_radiator ['name'] ,"...";
	$l_radiator_now = cometblue_receiveconf ( $l_radiator ['mac'], PIN );
	if ( $l_radiator_now == false ) {     
    echo "Error", PHP_EOL;
		continue;
	}
  echo "OK",PHP_EOL;
	$GLOBALS ['heating']['radiators'] [$l_idx] = array_merge( $l_radiator,  $l_radiator_now);
}

radiators_save();
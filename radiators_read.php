<?php
chdir ( dirname ( __FILE__ ) );

require_once 'inc/cometblue.php';
require_once 'config.php';
require_once 'inc/radiator.php';


var_export(cometblue_receiveconf ( $argv[ 1 ] , PIN ));
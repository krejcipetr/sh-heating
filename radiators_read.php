<?php
chdir ( dirname ( __FILE__ ) );

require_once 'config.php';
require_once 'inc/radiator.php';
require_once 'inc/cometblue_new.php';

var_export ( cometblue_receiveconf ( $argv[ 1 ] , PIN ));
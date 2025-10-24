<?php
chdir ( dirname ( __FILE__ ) );

require_once 'inc/cometblue.php';
require_once 'config.php';
require_once 'inc/radiator.php';


$l_radiator =  cometblue_receiveconf ( $argv[ 1 ] , PIN );
<?php

function easyesp8266_switch_on ( $a_params ) {
	$l_ps = explode(",", $a_params);
	file_get_contents(sprintf( "http://%s/control?cmd=relay,%s,1",$l_ps[0], $l_ps[1]));

}

function easyesp8266_switch_off ( $a_params ) {
	$l_ps = explode(",", $a_params);
	file_get_contents(sprintf( "http://%s/control?cmd=relay,%s,0",$l_ps[0], $l_ps[1]));
}


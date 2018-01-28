<?php

/**
 * Zjisti stav spinace na odpovidajicim zarizeni
 *
 * @param string $a_device
 * @return boolean
 */
function bt04a_getstate ( $a_device ) {
	$l_rfcomm = fopen ( '/dev/rfcomm' . $a_device, "w+b" );
	if ( $l_rfcomm === false ) {
		die ( "Nepodarilo se otevrit spojeni na kotel" );
	}
	// Zamek na zarizeni
	flock ( $l_rfcomm, LOCK_EX );
	fflush ( $l_rfcomm );
	// Prikaz na stav
	fwrite ( $l_rfcomm, base64_decode ( "r/0H3w==" ) );
	fflush ( $l_rfcomm );
	// Nacti vsechno pripravene do konce prikazu, ktery konci DF
	while ( ! feof ( $l_rfcomm ) && ($l_x = ord ( fgetc ( $l_rfcomm ) )) != 0xdf )
		;
	$l_state = ord ( fgetc ( $l_rfcomm ) );
	flock ( $l_rfcomm, LOCK_UN );
	fclose ( $l_rfcomm );
	return ( bool ) ($l_state == 1);
}

/**
 * Zapne spinac na zarizeni
 *
 * @param string $a_device
 */
function bt04a_on ( $a_device ) {
	file_put_contents ( '/dev/rfcomm' . $a_device, base64_decode ( "r/0A3w==" ), FILE_APPEND );
}

/**
 * Vyplne spinac na zarizeni
 *
 * @param string $a_device
 */
function bt04a_off ( $a_device ) {
	file_put_contents ( '/dev/rfcomm' . $a_device, base64_decode ( "r/0B3w==" ), FILE_APPEND );
}

<?php

/**
 * Dekofuje vytapeni jednoho dne z konfigurace hlavice
 *
 * @param string $a_definition
 * @return array
 */
function day_decode ( $a_definition ) {
	$l_casy = explode ( " ", $a_definition );

	$l_intervaly = array ();

	foreach ( array (0, 2, 4, 6 ) as $l_idxstart ) {
		if ( ! $l_casy [$l_idxstart] ) {
			continue;
		}
		$l_od = hexdec ( $l_casy [$l_idxstart] );
		$l_do = hexdec ( $l_casy [$l_idxstart + 1] );

		if ( $l_do <= $l_od ) {
			continue;
		}

		$l_od = sprintf ( "%02d:%02d", intval ( $l_od / 6 ), ($l_od % 6) * 10 );
		$l_do = sprintf ( "%02d:%02d", intval ( $l_do / 6 ), ($l_do % 6) * 10 );

		$l_intervaly [] = array ('from' => $l_od, 'to' => $l_do );
	}

	return $l_intervaly;
}

/**
 * Dekoduje dovolenou
 *
 * @param string $a_definition
 * @return NULL|array
 */
function dovolena_decode ( $a_definition ) {
	if ( $a_definition == "00 00 00 00 00 00 00 00 00" ) {
		return null;
	}

	$l_casti = explode ( " ", $a_definition );

	if ( $l_casti [0] == '80' ) {
		return null;
	}

	return array (
		'from' => sprintf ( "%02d/%02d/%02d %02d:00", hexdec ( $l_casti [2] ), hexdec ( $l_casti [1] ), hexdec ( $l_casti [3] ), hexdec ( $l_casti [0] ) % 128 ),
		'to' => sprintf ( "%02d/%02d/%02d %02d:00", hexdec ( $l_casti [6] ), hexdec ( $l_casti [5] ), hexdec ( $l_casti [7] ), hexdec ( $l_casti [4] ) % 128 ),
		'temp' => hexdec ( $l_casti [8] ) / 2 );
}

/**
 * Zakoduje den
 *
 * @param array $a_definition
 * @return string
 */
function day_encode ( $a_definition ) {
	$l_idxstart = 0;

	$l_intervaly = array_fill ( 0, 8, "00" );

	foreach ( $a_definition as $l_def ) {
		$l_od = explode ( ":", $l_def ['from'] );
		$l_od = $l_od [0] * 6 + $l_od [1] / 10;

		$l_do = explode ( ":", $l_def ['to'] );
		$l_do = $l_do [0] * 6 + $l_do [1] / 10;

		$l_intervaly [$l_idxstart ++] = "0x" . dechex ( $l_od );
		$l_intervaly [$l_idxstart ++] = "0x" . dechex ( $l_do );
	}

	return implode ( " ", $l_intervaly );
}

/**
 * Zakoduje dovolenou
 *
 * @param array $a_definition
 * @return string
 */
function dovolena_encode ( $a_definition ) {
	$l_data = array_fill ( 0, 9, "00" );

	if ( $a_definition == "null" || $a_definition === null ) {
		return implode ( " ", $l_data );
	}

	preg_match ( "/([0-9]{2})\\/([0-9]{2})\\/([0-9]{2}) ([0-9]{2})/", $a_definition ['from'], $l_p );

	$l_data [0] = "0x" . dechex ( $l_p [4] );
	$l_data [1] = "0x" . dechex ( $l_p [2] );
	$l_data [2] = "0x" . dechex ( $l_p [1] );
	$l_data [3] = "0x" . dechex ( $l_p [3] );

	preg_match ( "/([0-9]{2})\\/([0-9]{2})\\/([0-9]{2}) ([0-9]{2})/", $a_definition ['to'], $l_p );

	$l_data [4] = "0x" . dechex ( $l_p [4] );
	$l_data [5] = "0x" . dechex ( $l_p [2] );
	$l_data [6] = "0x" . dechex ( $l_p [1] );
	$l_data [7] = "0x" . dechex ( $l_p [3] );

	$l_data [8] = "0x" . dechex ( $a_definition ['temp'] * 2 );

	return implode ( " ", $l_data );
}

/**
 * Nacteni informace z hlavice pomoci rozsireni expect a btgatt-client
 *
 * @param string $a_mac
 * @param string $a_pin
 * @return boolean|array
 */
function cometblue_receiveconf ( $a_mac, $a_pin ) {
	$l_output = array ();

	/* Nacteni ze zažízení */
	try {

		ini_set ( "expect.timeout", 120 );
		ini_set ( "expect.loguser", 0 );

		$l_retry = 3;
		while ( true ) {
			$stream = fopen ( "expect://LC_ALL=en_US-UTF-8 LANG=en_US.UTF-8 exec btgatt-client -d " . $a_mac, "at" );
			$cases = array (array ("Connecting to device...", "connectionstarted" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "connectionstarted" :
					break;
				default :
					throw new Exception ( "Chyba" );
			}
			$cases = array (
				array ("Done", "OK" ),
				array ("Failed to connect: Device or resource busy", "Error" ),
				array ("Failed to connect: Transport endpoint is not connected", "Retry" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break 2;
				case EXP_TIMEOUT :
				case EXP_EOF :
				case "Retry" :
					fclose ( $stream );

					$l_retry --;
					if ( $l_retry < 0 ) {
						throw new Exception ( "Chyba" );
					}
					sleep ( 15 );
					continue;

				case "Error" :
				default :
					throw new Exception ( "Chyba" );
			}
		}

		$cases = array (array ("47e9ee30-47e9-11e4-8939-164230d1df67", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}

		ini_set ( "expect.timeout", 5 );

		fwrite ( $stream, "write-value 0x0048 " . $a_pin . "\n" );

		$cases = array (array ("Write successful", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}

		$cases = array (array ("Read value \([0-9]+ bytes\): ([^\n]+) \r?\n", "data", EXP_REGEXP ) );
		foreach ( array ('0x001b', '0x001d', '0x001f', '0x0021', '0x0023', '0x0025', '0x0027', '0x0029', '0x002b', '0x002d', '0x002f', '0x0031', '0x0033', '0x0035', '0x0037', '0x0039', '0x003b', '0x003d', '0x003f' ) as $l_idx => $l_handle ) {
			fwrite ( $stream, "read-value " . $l_handle . "\n" );

			switch ( expect_expectl ( $stream, $cases, $l_data ) ) {
				case "data" :
					break;
				default :
					throw new Exception ( "Chyba" );
			}
			$l_output [$l_idx] = $l_data [1];
		}
	} catch ( Exception $e ) {
		fclose ( $stream );
		return false;
	}

	// Poslani CTRL-C
	fwrite ( $stream, chr ( 3 ) );

	$cases = array (array ("Shutting down...", "OK" ) );
	switch ( expect_expectl ( $stream, $cases ) ) {
		case "OK" :
			break;
		default :
			throw new Exception ( "Chyba" );
	}

	fclose ( $stream );

	/* Zpracovani vystupu */

	$l_radiator = array ();

	// Pondeli
	$l_radiator ['pondeli'] = day_decode ( $l_output [1] );
	$l_radiator ['utery'] = day_decode ( $l_output [2] );
	$l_radiator ['streda'] = day_decode ( $l_output [3] );
	$l_radiator ['ctvrtek'] = day_decode ( $l_output [4] );
	$l_radiator ['patek'] = day_decode ( $l_output [5] );
	$l_radiator ['sobota'] = day_decode ( $l_output [6] );
	$l_radiator ['nedele'] = day_decode ( $l_output [7] );

	// Dovolena
	$l_radiator ['dovolena'] = dovolena_decode ( $l_output [8] );

	// Teploty
	$l_teploty = explode ( " ", $l_output [17] );
	$l_radiator ['required'] = hexdec ( $l_teploty [1] ) / 2;
	$l_radiator ['current'] = hexdec ( $l_teploty [0] ) / 2;
	$l_radiator ['comfort'] = hexdec ( $l_teploty [3] ) / 2;
	$l_radiator ['night'] = hexdec ( $l_teploty [2] ) / 2;
	$l_radiator ['offset'] = hexdec ( $l_teploty [4] );
	if ( $l_radiator ['offset'] > 128 ) {
		$l_radiator ['offset'] -= 256;
	}
	$l_radiator ['offset'] /= 2;
	$l_radiator ['window_detect'] = array ('sensivity' => hexdec ( $l_teploty [5] ), 'timer' => hexdec ( $l_teploty [6] ) );
	$l_radiator ['battery'] = hexdec ( $l_output [18] );
	$l_radiator ['lastdata'] = implode ( PHP_EOL, $l_output );

	return $l_radiator;
}

/**
 * Poslani konfigurace radiatoru do hlavice pomoci rozsireni expect a btgatt-client
 *
 * @param array $a_radiator
 */
function cometblue_sendconf ( $a_radiator, $a_pin ) {
	/* Zakodovani dat */
	$l_output = array ();

	// Aktualni cas a datum
	$l_date = getdate ();
	$l_output [] = sprintf ( "0x%s 0x%s 0x%s 0x%s 0x%s", dechex ( $l_date ['minutes'] ), dechex ( $l_date ['hours'] ), dechex ( $l_date ['mday'] ), dechex ( $l_date ['mon'] ), dechex ( $l_date ['year'] % 1000 ) );

	// Programator tydne
	$l_output [] = day_encode ( $a_radiator ['pondeli'] );
	$l_output [] = day_encode ( $a_radiator ['utery'] );
	$l_output [] = day_encode ( $a_radiator ['streda'] );
	$l_output [] = day_encode ( $a_radiator ['ctvrtek'] );
	$l_output [] = day_encode ( $a_radiator ['patek'] );
	$l_output [] = day_encode ( $a_radiator ['sobota'] );
	$l_output [] = day_encode ( $a_radiator ['nedele'] );

	//
	$l_output [] = dovolena_encode ( $a_radiator ['dovolena'] );
	$l_output [] = dovolena_encode ( null );
	$l_output [] = dovolena_encode ( null );
	$l_output [] = dovolena_encode ( null );
	$l_output [] = dovolena_encode ( null );
	$l_output [] = dovolena_encode ( null );
	$l_output [] = dovolena_encode ( null );
	$l_output [] = dovolena_encode ( null );

	// Flags
	$l_output [] = "0x80 00 00";

	// Teploty
	$a_radiator ['offset'] = ($a_radiator ['offset'] < 0) ? 256 + 2 * $a_radiator ['offset'] : 2 * $a_radiator ['offset'];
	$l_output [] = sprintf ( "0x80 0x%s 0x%s 0x%s 0x%s 0x%s 0x%s", dechex ( $a_radiator ['required'] * 2 ), dechex ( $a_radiator ['night'] * 2 ), dechex ( $a_radiator ['comfort'] * 2 ), dechex ( $a_radiator ['offset'] ),
			dechex ( $a_radiator ['window_detect'] ['sensivity'] ), dechex ( $a_radiator ['window_detect'] ['timer'] ) );

	/* Nahrání do zažízení */
	try {

		ini_set ( "expect.timeout", 120 );
		ini_set ( "expect.loguser", 0 );

		$l_retry = 3;
		while ( true ) {
			$stream = fopen ( "expect://LC_ALL=en_US-UTF-8 LANG=en_US.UTF-8 exec btgatt-client -d " . $a_mac, "at" );
			$cases = array (array ("Connecting to device...", "connectionstarted" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "connectionstarted" :
					break;
				default :
					throw new Exception ( "Chyba" );
			}
			$cases = array (
				array ("Done", "OK" ),
				array ("Failed to connect: Device or resource busy", "Error" ),
				array ("Failed to connect: Transport endpoint is not connected", "Retry" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break 2;
				case EXP_TIMEOUT :
				case EXP_EOF :
				case "Retry" :
					fclose ( $stream );

					$l_retry --;
					if ( $l_retry < 0 ) {
						throw new Exception ( "Chyba" );
					}
					sleep ( 15 );
					continue;

				case "Error" :
				default :
					throw new Exception ( "Chyba" );
			}
		}

		$cases = array (array ("47e9ee30-47e9-11e4-8939-164230d1df67", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}

		ini_set ( "expect.timeout", 5 );

		fwrite ( $stream, "write-long-value 0x0048 " . $a_pin . "\n" );

		$cases = array (array ("Write successful", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}

		$cases = array (array ("Write successful", "OK" ) );
		foreach ( array ('0x001b', '0x001d', '0x001f', '0x0021', '0x0023', '0x0025', '0x0027', '0x0029', '0x002b', '0x002d', '0x002f', '0x0031', '0x0033', '0x0035', '0x0037', '0x0039', '0x003b', '0x003d' ) as $l_idx => $l_handle ) {
			fwrite ( $stream, "write-value " . $l_handle . " " . $l_output [$l_idx] . "\n" );

			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break;
				default :
					throw new Exception ( "Chyba" );
			}
		}
	} catch ( Exception $e ) {
		fclose ( $stream );
		return false;
	}

	// Poslani CTRL-C
	fwrite ( $stream, chr ( 3 ) );

	$cases = array (array ("Shutting down...", "OK" ) );
	switch ( expect_expectl ( $stream, $cases ) ) {
		case "OK" :
			break;
		default :
			throw new Exception ( "Chyba" );
	}

	fclose ( $stream );

	return true;
}


<?php

/*
 * static let manualMode = StatusOptions(rawValue: 1)
 * static let antifrostActive = StatusOptions(rawValue: 1 << 4)
 * static let childlock = StatusOptions(rawValue: 1 << 7)
 * static let motorMoving = StatusOptions(rawValue: 1 << 8)
 * static let notReady = StatusOptions(rawValue: 1 << 9)
 * static let adapting = StatusOptions(rawValue: 1 << 10)
 * static let lowBattery = StatusOptions(rawValue: 1 << 11)
 * static let tempSatisfied = StatusOptions(rawValue: 1 << 19)
 */

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

		if ( $l_casy [$l_idxstart] == 0 ) {
			$l_od = "";
		}
		else {
			$l_od = hexdec ( $l_casy [$l_idxstart] );
			$l_od = sprintf ( "%02d:%02d", intval ( $l_od / 6 ), ($l_od % 6) * 10 );
		}

		if ( $l_casy [$l_idxstart] == 0 ) {
			$l_do = "";
		}
		else {
			$l_do = hexdec ( $l_casy [$l_idxstart + 1] );
			$l_do = sprintf ( "%02d:%02d", intval ( $l_do / 6 ), ($l_do % 6) * 10 );
		}
		$l_intervaly [] = array ('from' => $l_od, 'to' => $l_do );
	}
	usort ( $l_intervaly, function ( $a, $b ) {
		return strcmp ( $b ['from'], $a ['from'] );
	} );

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
		'from' => sprintf ( "%02d/%02d/%02d %02d", hexdec ( $l_casti [2] ), hexdec ( $l_casti [1] ), hexdec ( $l_casti [3] ), hexdec ( $l_casti [0] ) % 128 ),
		'to' => sprintf ( "%02d/%02d/%02d %02d", hexdec ( $l_casti [6] ), hexdec ( $l_casti [5] ), hexdec ( $l_casti [7] ), hexdec ( $l_casti [4] ) % 128 ),
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
		if ( $l_od [0] ) {
			$l_od = $l_od [0] * 6 + $l_od [1] / 10;
		}
		else {
			$l_od = 0;
		}

		$l_do = explode ( ":", $l_def ['to'] );
		if ( $l_do [0] ) {
			$l_do = $l_do [0] * 6 + $l_do [1] / 10;
		}
		else {
			$l_do = 0;
		}

		$l_intervaly [$l_idxstart ++] = sprintf ( "0x%02s", dechex ( $l_od ) );
		$l_intervaly [$l_idxstart ++] = sprintf ( "0x%02s", dechex ( $l_do ) );
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

	$l_p = '';
	preg_match ( "/([0-9]{2})\\/([0-9]{2})\\/([0-9]{2}) ([0-9]{2})/", $a_definition ['from'], $l_p );

	$l_data [0] = sprintf ( "0x%02s", dechex ( $l_p [4] ) );
	$l_data [1] = sprintf ( "0x%02s", dechex ( $l_p [2] ) );
	$l_data [2] = sprintf ( "0x%02s", dechex ( $l_p [1] ) );
	$l_data [3] = sprintf ( "0x%02s", dechex ( $l_p [3] ) );

	preg_match ( "/([0-9]{2})\\/([0-9]{2})\\/([0-9]{2}) ([0-9]{2})/", $a_definition ['to'], $l_p );

	$l_data [4] = sprintf ( "0x%02s", dechex ( $l_p [4] ) );
	$l_data [5] = sprintf ( "0x%02s", dechex ( $l_p [2] ) );
	$l_data [6] = sprintf ( "0x%02s", dechex ( $l_p [1] ) );
	$l_data [7] = sprintf ( "0x%02s", dechex ( $l_p [3] ) );

	$l_data [8] = sprintf ( "0x%02s", dechex ( $a_definition ['temp'] * 2 ) );

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

		ini_set ( "expect.timeout", 30 );
		ini_set ( "expect.loguser", 00);

		$l_retry = 3;
		while ( true ) {
			$stream = fopen ( "expect://LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 exec /usr/local/bin/btgatt-client -d " . $a_mac, "at" );
			if ( ! is_resource ( $stream ) ) {
				return false;
			}

			$cases = array (array ("Connecting to device...", "connectionstarted" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "connectionstarted" :
					break;
				default :
					throw new Exception ( "Chyba connect" );
			}
			$cases = array (array ("Done", "OK" ), array ("Failed to connect: Device or resource busy", "Retry" ), array ("Failed to connect: Transport endpoint is not connected", "Retry" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break;
				case EXP_TIMEOUT :
				case EXP_EOF :
				case "Retry" :
					fclose ( $stream );

					$l_retry --;
					if ( $l_retry < 0 ) {
						throw new Exception ( "Chyba connect" );
					}
					sleep ( 15 );
					continue 2;

				case "Error" :
				default :
					throw new Exception ( "Chyba" );
			}

			$cases = array (array ("type: primary, uuid: 47e9ee00-47e9-11e4-8939-164230d1df67", "OK" ), array ("GATT discovery procedures failed", "Retry" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break 2;
				case "Retry" :
					fclose ( $stream );

					$l_retry --;
					if ( $l_retry < 0 ) {
						throw new Exception ( "Chyba" );
					}
					sleep ( 15 );
					continue 2;
				default :
					throw new Exception ( "Chyba" );
			}
		}

		$l_pins = array ();
		$cases = array (array ("value: ([^,]+),", "PIN", EXP_REGEXP ), array ('[GATT client]', 'END', EXP_EXACT ) );
		while ( true ) {
			unset($l_match);
			$l_x = expect_expectl ( $stream, $cases, $l_match );
			switch ( $l_x ) {
			case "PIN" :
					$l_pins [] = $l_match [1];
					break;

				case 'END' :
				case EXP_TIMEOUT :
					break 2;

				default :
					throw new Exception ( "Chyba" );
			}
		}

		$l_pinaddr = array_pop ( $l_pins );

		fwrite ( $stream, "write-value " . $l_pinaddr . " " . $a_pin . "\n" );

		$cases = array (array ("Write successful", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}

		$cases = array (array ("Read value \([0-9]+ bytes\): ([^\n]+) \r?\n", "data", EXP_REGEXP ), array ("Read value: 0 bytes", 'nodata', EXP_EXACT ) );
		foreach ( $l_pins as $l_idx => $l_handle ) {
			fwrite ( $stream, "read-value " . $l_handle . "\n" );

			switch ( expect_expectl ( $stream, $cases, $l_data ) ) {
				case "data" :
					$l_output [$l_idx] = $l_data [1];
					break;
				case 'nodata' :
					break;
				default :
					throw new Exception ( "Chyba" );
			}
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
	} catch ( Exception $e ) {
		fprintf ( STDERR, $e->getTraceAsString () . PHP_EOL . $e->getMessage () );
		return false;
	}
	finally {
		is_resource($stream) && fclose ( $stream );

		system("bluetoothctl disconnect ". $a_mac);
	}


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
	$l_radiator ['current'] = hexdec ( $l_teploty [0] ) / 2;
	$l_radiator ['required'] = hexdec ( $l_teploty [1] ) / 2;
	$l_radiator ['night'] = hexdec ( $l_teploty [2] ) / 2;
	$l_radiator ['comfort'] = hexdec ( $l_teploty [3] ) / 2;
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

	/* Nahrání do zažízení */
	try {

		ini_set ( "expect.timeout", 30 );
		ini_set ( "expect.loguser", 0 );

		$l_retry = 3;
		while ( true ) {
			$stream = fopen ( "expect://LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 exec /usr/local/bin/btgatt-client -d " . $a_radiator ['mac'], "at" );
			if ( ! is_resource ( $stream ) ) {
				return false;
			}
			$cases = array (array ("Connecting to device...", "connectionstarted" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "connectionstarted" :
					break;
				default :
					throw new Exception ( "Chyba" );
			}
			$cases = array (array ("Done", "OK" ), array ("Failed to connect: Device or resource busy", "Retry" ), array ("Failed to connect: Transport endpoint is not connected", "Retry" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break;
				case EXP_TIMEOUT :
				case EXP_EOF :
				case "Retry" :
					fclose ( $stream );

					$l_retry --;
					if ( $l_retry < 0 ) {
						throw new Exception ( "Chyba" );
					}
					sleep ( 15 );
					continue 2;

				case "Error" :
				default :
					throw new Exception ( "Chyba" );
			}

			$cases = array (array ("type: primary, uuid: 47e9ee00-47e9-11e4-8939-164230d1df67", "OK" ), array ("GATT discovery procedures failed", "Retry" ) );
			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break 2;
				case "Retry" :
					fclose ( $stream );

					$l_retry --;
					if ( $l_retry < 0 ) {
						throw new Exception ( "Chyba" );
					}
					sleep ( 15 );
					continue 2;
				default :
					throw new Exception ( "Chyba" );
			}
		}

		$l_pins = array ();
		$cases = array (array ("value: ([^,]+)", "PIN", EXP_REGEXP ), array ('[GATT client]', 'END', EXP_EXACT ) );
		while ( true ) {
			switch ( expect_expectl ( $stream, $cases, $l_match ) ) {
				case "PIN" :
					$l_pins [] = $l_match [1];
					break;

				case 'END' :
				case EXP_TIMEOUT :
					break 2;

				default :
					throw new Exception ( "Chyba" );
			}
		}

		$l_pinaddr = array_pop ( $l_pins );

		fwrite ( $stream, "write-value " . $l_pinaddr . " " . $a_pin . "\n" );
		$cases = array (array ("Write successful", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}

		// Nacti hodnotu stavu a uprav ji podle pozadovanych bitu, jinak ji nech
		$cases = array (array ("Read value \([0-9]+ bytes\): ([^\n]+) \r?\n", "data", EXP_REGEXP ), array ("Read value: 0 bytes", 'nodata', EXP_EXACT ) );
		foreach ( array (16 => $l_pins [16], 17 => $l_pins [17] ) as $l_idx => $l_handle ) {
			fwrite ( $stream, "read-value " . $l_handle . "\n" );

			switch ( expect_expectl ( $stream, $cases, $l_data ) ) {
				case "data" :
					$l_source [$l_idx] = explode ( " ", $l_data [1] );
					break;
				case 'nodata' :
					break;
				default :
					throw new Exception ( "Chyba" );
			}
		}

		// priprav data
		/* Zakodovani dat */
		$l_output = array ();

		// Aktualni cas a datum
		$l_date = getdate ();
		$l_output [] = sprintf ( "0x%02s 0x%02s 0x%02s 0x%02s 0x%02s", dechex ( $l_date ['minutes'] ), dechex ( $l_date ['hours'] ), dechex ( $l_date ['mday'] ), dechex ( $l_date ['mon'] ), dechex ( $l_date ['year'] % 1000 ) );

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
		// BIT_MANUAL = 0x01
		// BIT_LOCKED = 0x80
		// BIT_WINDOW = 0x10
		$l_mode = hexdec ( $l_source [16] [0] );
		if ( $a_radiator ['mode_manual'] ?? 0) {
			$l_mode |= 0x01;
		}
		else {
			$l_mode &= 254;
		}
		// Zamcen neni
		$l_output [] = sprintf ( "0x%02s 0x%02s 0x%02s", dechex ( $l_mode ), $l_source [16] [1], $l_source [16] [2] );

		// Teploty
		$l_offset = ($a_radiator ['offset'] < 0) ? (256 + 2 * $a_radiator ['offset']) : (2 * $a_radiator ['offset']);
		$l_output [] = sprintf ( "0x%02s 0x%02s 0x%02s 0x%02s 0x%02s 0x%02s 0x%02s", $l_source [17] [0], dechex ( $a_radiator ['required'] * 2 ), dechex ( $a_radiator ['night'] * 2 ), dechex ( $a_radiator ['comfort'] * 2 ),
				dechex ( $l_offset ), dechex ( $a_radiator ['window_detect'] ['sensivity'] ), dechex ( $a_radiator ['window_detect'] ['timer'] ) );

		// Znovu odemceni 2x
		fwrite ( $stream, "write-value " . $l_pinaddr . " " . $a_pin . "\n" );
		$cases = array (array ("Write successful", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}
		fwrite ( $stream, "write-value " . $l_pinaddr . " " . $a_pin . "\n" );
		$cases = array (array ("Write successful", "OK" ) );
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}

		// Zapis hodnoty
		$cases = array (array ("Write successful", "OK" ) );
		foreach ( $l_pins as $l_idx => $l_handle ) {
			if ( ! array_key_exists ( $l_idx, $l_output ) ) {
				continue;
			}

			fwrite ( $stream, "write-value " . $l_handle . " " . $l_output [$l_idx] . "\n" );

			switch ( expect_expectl ( $stream, $cases ) ) {
				case "OK" :
					break;
				default :
					throw new Exception ( "Chyba " . $l_handle );
			}
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
	} catch ( Exception $e ) {
		fprintf ( STDERR, $e->getTraceAsString () . PHP_EOL . $e->getMessage () );
		fwrite ( $stream, chr ( 3 ) );
		return false;
	}
	finally {
		is_resource($stream) && fclose ( $stream );
		system("bluetoothctl disconnect ". $a_radiator['mac']);
	}

	return true;
}


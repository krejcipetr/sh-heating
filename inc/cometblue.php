<?php


const SERVICE = "47e9ee00-47e9-11e4-8939-164230d1df67";

const CHARACTERISTIC_DATETIME = ["47e9ee01-47e9-11e4-8939-164230d1df67", 5];

const CHARACTERISTIC_MONDAY = ["47e9ee10-47e9-11e4-8939-164230d1df67", 8];
const CHARACTERISTIC_TUESDAY = ["47e9ee11-47e9-11e4-8939-164230d1df67", 8];
const CHARACTERISTIC_WEDNESDAY = ["47e9ee12-47e9-11e4-8939-164230d1df67", 8];
const CHARACTERISTIC_THURSDAY = ["47e9ee13-47e9-11e4-8939-164230d1df67", 8];
const CHARACTERISTIC_FRIDAY = ["47e9ee14-47e9-11e4-8939-164230d1df67", 8];
const CHARACTERISTIC_SATURDAY = ["47e9ee15-47e9-11e4-8939-164230d1df67", 8];
const CHARACTERISTIC_SUNDAY = ["47e9ee16-47e9-11e4-8939-164230d1df67", 8];

const CHARACTERISTIC_HOLIDAY_1 = ["47e9ee20-47e9-11e4-8939-164230d1df67", 9];
const CHARACTERISTIC_HOLIDAY_2 = ["47e9ee21-47e9-11e4-8939-164230d1df67", 9];
const CHARACTERISTIC_HOLIDAY_3 = ["47e9ee22-47e9-11e4-8939-164230d1df67", 9];
const CHARACTERISTIC_HOLIDAY_4 = ["47e9ee23-47e9-11e4-8939-164230d1df67", 9];
const CHARACTERISTIC_HOLIDAY_5 = ["47e9ee24-47e9-11e4-8939-164230d1df67", 9];
const CHARACTERISTIC_HOLIDAY_6 = ["47e9ee25-47e9-11e4-8939-164230d1df67", 9];
const CHARACTERISTIC_HOLIDAY_7 = ["47e9ee26-47e9-11e4-8939-164230d1df67", 9];
const CHARACTERISTIC_HOLIDAY_8 = ["47e9ee27-47e9-11e4-8939-164230d1df67", 9];

const CHARACTERISTIC_SETTINGS = ["47e9ee2a-47e9-11e4-8939-164230d1df67", 3];
const CHARACTERISTIC_TEMPERATURE = ["47e9ee2b-47e9-11e4-8939-164230d1df67", 7];
const CHARACTERISTIC_BATTERY = ["47e9ee2c-47e9-11e4-8939-164230d1df67", 1];
const CHARACTERISTIC_UNKNOWN2 = "47e9ee2d-47e9-11e4-8939-164230d1df67";
const CHARACTERISTIC_UNKNOWN3 = "47e9ee2e-47e9-11e4-8939-164230d1df67";
const CHARACTERISTIC_PIN = "47e9ee30-47e9-11e4-8939-164230d1df67";

const UNCHANGED_VALUE = 0x80;

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
 * Dekoduje vytapeni jednoho dne z konfigurace hlavice
 *
 * @param string $a_definition
 * @return array
 */
function day_decode ( $l_casy ) {

	$l_intervaly = array();

	foreach ( array(0, 2, 4, 6) as $l_idxstart ) {
		if ( !$l_casy [ $l_idxstart ] ) {
			continue;
		}

		if ( $l_casy [ $l_idxstart ] == 0 ) {
			$l_od = "";
		}
		else {
			$l_od = ($l_casy [ $l_idxstart ]);
			$l_od = sprintf ( "%02d:%02d", intval ( $l_od / 6 ), ($l_od % 6) * 10 );
		}

		if ( $l_casy [ $l_idxstart ] == 0 ) {
			$l_do = "";
		}
		else {
			$l_do = ($l_casy [ $l_idxstart + 1 ]);
			$l_do = sprintf ( "%02d:%02d", intval ( $l_do / 6 ), ($l_do % 6) * 10 );
		}
		$l_intervaly [] = array('from' => $l_od, 'to' => $l_do);
	}
	usort ( $l_intervaly, function ( $a, $b ) {
		return strcmp ( $b [ 'from' ], $a [ 'from' ] );
	} );

	return $l_intervaly;
}

/**
 * Dekoduje dovolenou
 *
 * @param string $a_definition
 * @return NULL|array
 */
function dovolena_decode ( $l_casti ) {

	if ( $l_casti [ 2 ] == '00' ) {
		return null;
	}

	return array(
		'from' => sprintf ( "%02d/%02d/%02d %02d", ($l_casti [ 2 ]), ($l_casti [ 1 ]), ($l_casti [ 3 ]), ($l_casti [ 0 ]) % 24 ),
		'to'   => sprintf ( "%02d/%02d/%02d %02d", ($l_casti [ 6 ]), ($l_casti [ 5 ]), ($l_casti [ 7 ]), ($l_casti [ 4 ]) % 24 ),
		'temp' => ($l_casti [ 8 ]) / 2);
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
		$l_od = explode ( ":", $l_def [ 'from' ] );
		if ( $l_od [ 0 ] ) {
			$l_od = $l_od [ 0 ] * 6 + $l_od [ 1 ] / 10;
		}
		else {
			$l_od = 0;
		}

		$l_do = explode ( ":", $l_def [ 'to' ] );
		if ( $l_do [ 0 ] ) {
			$l_do = $l_do [ 0 ] * 6 + $l_do [ 1 ] / 10;
		}
		else {
			$l_do = 0;
		}

		$l_intervaly [ $l_idxstart++ ] = $l_od;
		$l_intervaly [ $l_idxstart++ ] = $l_do;
	}

	return $l_intervaly;
}

/**
 * Zakoduje dovolenou
 *
 * @param array $a_definition
 * @return array
 */
function dovolena_encode ( $a_definition ) {
	$l_data = array_fill ( 0, 9, 0 );

	if ( $a_definition == "null" || $a_definition === null ) {
		return $l_data;
	}

	$l_p = '';
	preg_match ( "/([0-9]{2})\\/([0-9]{2})\\/([0-9]{2}) ([0-9]{2})/", $a_definition [ 'from' ], $l_p );

	$l_data [ 0 ] = $l_p [ 4 ];
	$l_data [ 1 ] = $l_p [ 2 ];
	$l_data [ 2 ] = $l_p [ 1 ];
	$l_data [ 3 ] = $l_p [ 3 ];

	preg_match ( "/([0-9]{2})\\/([0-9]{2})\\/([0-9]{2}) ([0-9]{2})/", $a_definition [ 'to' ], $l_p );

	$l_data [ 4 ] = $l_p [ 4 ];
	$l_data [ 5 ] = $l_p [ 2 ];
	$l_data [ 6 ] = $l_p [ 1 ];
	$l_data [ 7 ] = $l_p [ 3 ];

	$l_data [ 8 ] = $a_definition [ 'temp' ] * 2;

	return $l_data;
}

function cometblue_open ( $a_mac, $a_pin, $a_max_retries = 3 , $a_pins = 1) {
	/* Nacteni ze zažízení */
	ini_set ( "expect.timeout", 30 );
	ini_set ( "expect.loguser", testing ? 1 : 0 );

	$l_retry = $a_max_retries;
	while ( true ) {
		$stream = fopen ( "expect://LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 exec /usr/local/bin/btgatt-client -d " . $a_mac, "at" );
		if ( !is_resource ( $stream ) ) {
			return false;
		}

		$cases = array(array("Connecting to device...", "connectionstarted"));
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "connectionstarted" :
				break;
			default :
				throw new Exception ( "Chyba connect" );
		}
		$cases = array(array("Done", "OK"), array("Failed to connect: Device or resource busy", "Retry"), array("Failed to connect: Transport endpoint is not connected", "Retry"));
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			case EXP_TIMEOUT :
			case EXP_EOF :
			case "Retry" :
				fclose ( $stream );

				$l_retry--;
				if ( $l_retry < 0 ) {
					throw new Exception ( "Chyba connect" );
				}
				sleep ( 15 );
				continue 2;

			case "Error" :
			default :
				throw new Exception ( "Chyba" );
		}

		$cases = array(array("type: primary, uuid: " . SERVICE, "OK"), array("GATT discovery procedures failed", "Retry"));
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break 2;
			case "Retry" :
				fclose ( $stream );

				$l_retry--;
				if ( $l_retry < 0 ) {
					throw new Exception ( "Chyba" );
				}
				sleep ( 15 );
				continue 2;
			default :
				throw new Exception ( "Chyba" );
		}
	}

	$l_btdefs = array();
	$cases = array(array("value: ([^,]+), props: 0x0[a8], ext_props: 0x0000, uuid: ([a-z0-9-]+)", "DEFINICE", EXP_REGEXP), array('[GATT client]', 'END', EXP_EXACT));
	while ( true ) {
		$l_match = array();
		$l_x = expect_expectl ( $stream, $cases, $l_match );
		switch ( $l_x ) {
			case "DEFINICE" :
				$l_btdefs [ $l_match[ 2 ] ] = $l_match [ 1 ];
				break;

			case 'END' :
			case "EXP_TIMEOUT" :
				break 2;

			default :
				throw new Exception ( "Chyba" );
		}
	}

	// Zadani PINU
	for ( $i = 0; $i < $a_pins; ++$i ) {
		fwrite ( $stream, "write-value " . $l_btdefs[ CHARACTERISTIC_PIN ] . " " . $a_pin . "\n" );

		$cases = array(array("Write successful", "OK"));
		switch ( expect_expectl ( $stream, $cases ) ) {
			case "OK" :
				break;
			default :
				throw new Exception ( "Chyba" );
		}
	}
	return [$stream, $l_btdefs];
}

function cometblue_close ( $stream ) {
	if ( !is_resource ( $stream ) ) {
		return;
	}
	fwrite ( $stream, chr ( 3 ) );

	$cases = array(array("Shutting down...", "OK"));
	switch ( expect_expectl ( $stream, $cases ) ) {
		case "OK" :
			break;
		default :
			throw new Exception ( "Chyba" );
	}
}

function cometblue_receivevalue ( $stream, $l_btdefs, $a_uuid ) {
	fwrite ( $stream, 'read-value ' . $l_btdefs[ $a_uuid ] . "\n" );

	$cases = array(array("Read value \([0-9]+ bytes\): ([^\n]+) \r?\n", "data", EXP_REGEXP), array("Read value: 0 bytes", 'nodata', EXP_EXACT));
	$l_data = array();

	switch ( expect_expectl ( $stream, $cases, $l_data ) ) {
		case "data" :
			$l_data = explode ( " ", $l_data [ 1 ] );
			array_walk ( $l_data, function ( &$v ) {
				$v = hexdec ( trim ( $v ) );
			} );
			return $l_data;
		case
		"nodata" :
			return null;
		default :
			throw new Exception ( "Chyba" );
	}
}

function cometblue_writevalue ( $stream, $l_btdefs, $l_outputdef ) {
	array_walk ( $l_outputdef[ 1 ], function ( &$v ) {
		$v = '0x' . dechex ( $v );
	} );

	$l_hanbdle = $l_btdefs[ $l_outputdef[ 0 ] ];
	fwrite ( $stream, "write-value " . $l_hanbdle . " " . implode ( " ", $l_outputdef[ 1 ] ) . "\n" );

	$cases = array(array("Write successful", "OK"));
	switch ( expect_expectl ( $stream, $cases ) ) {
		case "OK" :
			break;
		default :
			throw new Exception ( "Chyba " . $l_hanbdle );
	}
}


/**
 * Nacteni informace z hlavice pomoci rozsireni expect a btgatt-client
 *
 * @param string $a_mac
 * @param string $a_pin
 * @return boolean|array
 */
function cometblue_receiveconf ( $a_mac, $a_pin ) {
	try {

		list( $stream, $l_btdefs ) = cometblue_open ( $a_mac, $a_pin );

		$l_readdefs = [
			CHARACTERISTIC_DATETIME,
			CHARACTERISTIC_MONDAY,
			CHARACTERISTIC_TUESDAY,
			CHARACTERISTIC_WEDNESDAY,
			CHARACTERISTIC_THURSDAY,
			CHARACTERISTIC_FRIDAY,
			CHARACTERISTIC_SATURDAY,
			CHARACTERISTIC_SUNDAY,
			CHARACTERISTIC_TEMPERATURE,
			CHARACTERISTIC_BATTERY,
			CHARACTERISTIC_SETTINGS,
			CHARACTERISTIC_HOLIDAY_1
		];

		$l_output = array();
		foreach ( $l_readdefs as $l_readdef ) {
			$l_output [ $l_readdef[ 0 ] ] = cometblue_receivevalue ( $stream, $l_btdefs, $l_readdef[ 0 ] );
		}
	}
	catch ( Exception $e ) {
		fprintf ( STDERR, $e->getTraceAsString () . PHP_EOL . $e->getMessage () );
		return false;
	}
	finally {
		cometblue_close ( $stream );
		system ( "bluetoothctl disconnect " . $a_mac );

		is_resource ( $stream ) && fclose ( $stream );
	}


	/* Zpracovani vystupu */

	$l_radiator = array();

// Programator
	$l_radiator [ 'pondeli' ] = day_decode ( $l_output [ CHARACTERISTIC_MONDAY[ 0 ] ] );
	$l_radiator [ 'utery' ] = day_decode ( $l_output [ CHARACTERISTIC_TUESDAY[ 0 ] ] );
	$l_radiator [ 'streda' ] = day_decode ( $l_output [ CHARACTERISTIC_WEDNESDAY[ 0 ] ] );
	$l_radiator [ 'ctvrtek' ] = day_decode ( $l_output [ CHARACTERISTIC_THURSDAY[ 0 ] ] );
	$l_radiator [ 'patek' ] = day_decode ( $l_output [ CHARACTERISTIC_FRIDAY[ 0 ] ] );
	$l_radiator [ 'sobota' ] = day_decode ( $l_output [ CHARACTERISTIC_SATURDAY[ 0 ] ] );
	$l_radiator [ 'nedele' ] = day_decode ( $l_output [ CHARACTERISTIC_SUNDAY[ 0 ] ] );

// Dovolena
	$l_radiator [ 'dovolena' ] = dovolena_decode ( $l_output[ CHARACTERISTIC_HOLIDAY_1[ 0 ] ] );

// Teploty
	$l_teploty = $l_output [ CHARACTERISTIC_TEMPERATURE[ 0 ] ];
	$l_radiator [ 'current' ] = ($l_teploty [ 0 ]) / 2;
	$l_radiator [ 'required' ] = ($l_teploty [ 1 ]) / 2;
	$l_radiator [ 'night' ] = ($l_teploty [ 2 ]) / 2;
	$l_radiator [ 'comfort' ] = ($l_teploty [ 3 ]) / 2;
	$l_radiator [ 'offset' ] = ($l_teploty [ 4 ]);
	if ( $l_radiator [ 'offset' ] > 128 ) {
		$l_radiator [ 'offset' ] -= 256;
	}
	$l_radiator [ 'offset' ] /= 2;
	$l_radiator [ 'window_detect' ] = array('sensivity' => ($l_teploty [ 5 ]), 'timer' => ($l_teploty [ 6 ]));

// Info
	$l_radiator [ 'battery' ] = hexdec ( $l_output[ CHARACTERISTIC_BATTERY[ 0 ] ] );
	$l_radiator [ 'lastdata' ] = serialize ( $l_output );

	return $l_radiator;
}


/**
 * Poslani konfigurace radiatoru do hlavice pomoci rozsireni expect a btgatt-client
 *
 * @param array $a_radiator
 */
function cometblue_sendconf ( $a_radiator, $a_pin ) {

	try {

		list( $stream, $l_btdefs ) = cometblue_open ( $a_radiator[ 'mac' ], $a_pin, 3 ,2 );

		$l_settings = cometblue_receivevalue ( $stream, $l_btdefs, CHARACTERISTIC_SETTINGS[ 0 ] );

		// priprav data
		/* Zakodovani dat */
		$l_output = array();

		// Aktualni cas a datum
		$l_date = getdate ();
		$l_output [] = [CHARACTERISTIC_DATETIME[ 0 ], [$l_date [ 'minutes' ], $l_date [ 'hours' ], $l_date [ 'mday' ], $l_date [ 'mon' ], $l_date [ 'year' ] % 1000]];

		// Programator tydne
		$l_output [] = [CHARACTERISTIC_MONDAY[ 0 ], day_encode ( $a_radiator [ 'pondeli' ] )];
		$l_output [] = [CHARACTERISTIC_TUESDAY[ 0 ], day_encode ( $a_radiator [ 'utery' ] )];
		$l_output [] = [CHARACTERISTIC_WEDNESDAY[ 0 ], day_encode ( $a_radiator [ 'streda' ] )];
		$l_output [] = [CHARACTERISTIC_THURSDAY[ 0 ], day_encode ( $a_radiator [ 'ctvrtek' ] )];
		$l_output [] = [CHARACTERISTIC_FRIDAY[ 0 ], day_encode ( $a_radiator [ 'patek' ] )];
		$l_output [] = [CHARACTERISTIC_SATURDAY[ 0 ], day_encode ( $a_radiator [ 'sobota' ] )];
		$l_output [] = [CHARACTERISTIC_SUNDAY[ 0 ], day_encode ( $a_radiator [ 'nedele' ] )];

		// Dolelena
		$l_output[] = [CHARACTERISTIC_HOLIDAY_1[ 0 ], dovolena_encode ( $a_radiator [ 'dovolena' ] )];

		// Teploty
		$l_offset = ($a_radiator [ 'offset' ] < 0) ? (256 + 2 * $a_radiator [ 'offset' ]) : (2 * $a_radiator [ 'offset' ]);
		$l_output [] = [CHARACTERISTIC_TEMPERATURE[ 0 ], [UNCHANGED_VALUE, $a_radiator [ 'required' ] * 2, $a_radiator [ 'night' ] * 2, $a_radiator [ 'comfort' ] * 2,
		                                                  $l_offset, $a_radiator [ 'window_detect' ] [ 'sensivity' ], $a_radiator [ 'window_detect' ] [ 'timer' ]]];

		// AUTO MODE ->MANUAL->AUTO
		$l_output [] = [CHARACTERISTIC_SETTINGS[ 0 ], [($l_settings | 0x1), $l_settings[ 1 ], $l_settings[ 2 ]]];
		$l_output [] = [CHARACTERISTIC_SETTINGS[ 0 ], [($l_settings & 0xfe), $l_settings[ 1 ], $l_settings[ 2 ]]];

		// Vlastni zapsani do zarizeni
		foreach ( $l_output as $l_outdef ) {
			cometblue_writevalue ( $stream, $l_btdefs, $l_outdef );
		}

	}
	catch ( Exception $e ) {
		fprintf ( STDERR, $e->getTraceAsString () . PHP_EOL . $e->getMessage () );
		return false;
	}
	finally {
		cometblue_close ( $stream );
		system ( "bluetoothctl disconnect " . $a_radiator[ 'mac' ] );

		is_resource ( $stream ) && fclose ( $stream );
	}

	return true;
}

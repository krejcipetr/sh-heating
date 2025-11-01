<?php
require_once 'sem.php';

/**
 * Informace o radiatoru behem rizeni
 *
 * @param array $a_radiator
 * @return string
 */
function control_info_radiator ( $a_radiator ) {
	$l_day = mktime ( 0, 0, 0 );
	$l_cas = ($a_radiator [ 'control' ] [ 'runningfrom' ]) ? ((time () - strtotime ( $a_radiator [ 'control' ] [ 'runningfrom' ] )) / 60) : 0;
	return sprintf ( "Name: %s" . PHP_EOL, $a_radiator [ 'name' ] ) . (($a_radiator [ 'current' ] != $a_radiator [ 'previous' ]) ? sprintf ( "Current temp: %.1f=>%.1f" . PHP_EOL, $a_radiator [ 'previous' ], $a_radiator [ 'current' ] ) : sprintf (
			"Current temp: %.1f" . PHP_EOL, $a_radiator [ 'current' ] )) . sprintf ( "Required temp: %.1f" . PHP_EOL, $a_radiator [ 'required' ] ) . sprintf ( "Direction: %d" . PHP_EOL, $a_radiator [ 'control' ] [ 'direction' ] ) . sprintf (
			"Heating: %d" . PHP_EOL, $a_radiator [ 'control' ] [ 'heating' ] ) . sprintf ( "State: %s" . PHP_EOL, $a_radiator [ 'control' ] [ 'state' ] ) . sprintf ( "Today: %.1f hours" . PHP_EOL,
			floatval ( ($a_radiator [ 'statistic' ] [ 'day' ] [ $l_day ] + $l_cas) / 60 ) ) . sprintf ( "Summary: %.1f hours" . PHP_EOL, floatval ( ($a_radiator [ 'statistic' ] [ 'summary' ] + $l_cas) / 60 ) ) . sprintf ( "Running from: %s" . PHP_EOL,
			$a_radiator [ 'control' ] [ 'runningfrom' ] ) . sprintf ( "Time: %.1f min" . PHP_EOL, floatval ( $l_cas ) );
}

/**
 *
 * @param array $a_source
 * @return string
 */
function control_info_source ( $a_source ) {
	$l_cas = ($a_source [ 'runningfrom' ]) ? ((time () - strtotime ( $a_source [ 'runningfrom' ] )) / 60) : 0;
	return sprintf ( "Name: %s" . PHP_EOL, $a_source [ 'name' ] ) . sprintf ( "State: %b" . PHP_EOL, $a_source [ 'state' ] ) . sprintf ( "Today: %.1f hours" . PHP_EOL,
			floatval ( ($a_source [ 'statistic' ] [ 'day' ] [ mktime ( 0, 0, 0 ) ] + $l_cas) / 60 ) ) . sprintf ( "Summary: %.1f hours" . PHP_EOL, floatval ( ($a_source [ 'statistic' ] [ 'summary' ] + $l_cas) / 60 ) ) . sprintf (
			"Running from: %s" . PHP_EOL, $a_source [ 'runningfrom' ] ) . sprintf ( "Time: %.1f min" . PHP_EOL, floatval ( $l_cas ) );
}

/**
 * Ulozi globalni promenou na FS
 */
function radiators_save () {
	array_walk ( $GLOBALS [ 'heating' ][ 'radiators' ], 'radiator_clean' );

	file_put_contents ( $GLOBALS [ 'logs' ] . 'radiators_logs.json', file_get_contents ( $GLOBALS [ 'logs' ] . 'radiators.json' ), FILE_APPEND );
	file_put_contents ( $GLOBALS [ 'logs' ] . 'radiators.json', json_encode ( $GLOBALS [ 'heating' ] ) );

	semup ();
}

function radiators_load () {
	semdown ();
	$GLOBALS [ 'heating' ] = json_decode ( file_get_contents ( $GLOBALS [ 'logs' ] . 'radiators.json' ), true );

	array_walk ( $GLOBALS [ 'heating' ][ 'radiators' ], 'radiator_clean' );
}


/**
 * Vrati radiator podle jmena
 *
 * @param string $a_name
 * @return unknown|boolean
 */
function & radiator_getbyname ( $a_name ) {
	foreach ( array_keys ( $GLOBALS [ 'heating' ] [ 'radiators' ] ) as $l_idx ) {
		if ( $GLOBALS [ 'heating' ] [ 'radiators' ] [ $l_idx ][ 'name' ] == $a_name ) {
			return $GLOBALS [ 'heating' ] [ 'radiators' ] [ $l_idx ];
		}
	}
	return false;
}

function radiator_clean ( &$l_radiator ) {
	foreach ( ['pondeli', 'utery', 'streda', 'ctvrtek', 'patek', 'sobota', 'nedele'] as $x ) {
		$l_radiator[ $x ] = array_filter ( $l_radiator[ $x ],
			function ( $v ) {
				return $v[ 'from' ] != '' && $v[ 'to' ] != '';
			}
		);
		usort ( $l_radiator[ $x ], function ( $a, $b ): int {
			return strcmp ( $a[ 'from' ], $b[ 'from' ] );
		} );
	}

}
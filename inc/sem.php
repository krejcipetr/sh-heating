<?php

/**
 * @return integer
 */
function semget () {
	! file_exists ( '/tmp/heating' ) && touch ( "/tmp/heating" ) && chmod ( '/tmp/heating', 0777 );
	$l_id = ftok ( "/tmp/heating", "P" );
	return sem_get ( $l_id, 1 );
}

/**
 *
 */
function semdown () {
	$GLOBALS ['sem'] = semget ();
	sem_acquire ( $GLOBALS ['sem'] );
}

/**
 *
 */
function semup () {
	sem_release ( $GLOBALS ['sem'] );
}


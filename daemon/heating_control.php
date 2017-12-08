<?php
chdir ( dirname ( __FILE__ ) . '/..' );

require_once 'config.php';
require_once 'inc/bt04a.php';
require_once 'inc/cometblue.php';
require_once 'inc/radiator.php';

// Nacteni radiatoru

radiators_load ();
// Zjisteni aktualniho stavu zapnmuti kotle
printf ( "Reading state of heat source\n" );
foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
	unset ( $l_source );
	$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];
	$l_source ['state'] = bt04a_getstate ( $l_source ['dev'] );
	printf ( "Current state of %s: %b\n", $l_source ['name'], $l_source ['state'] );
}

// Nacteni aktualniho stavu radiatoru
printf ( "\nReading state of radiators\n" );
foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
	unset ( $l_radiator );
	$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

	if ( isset ( $l_radiator ['control'] ) ) {
		continue;
	}

	echo $l_radiator ['mac'], '=', $l_radiator ['name'], "...";
	$l_radiator_now = cometblue_receiveconf ( $l_radiator ['mac'], PIN );
	if ( $l_radiator_now == false ) {
		echo "Error", PHP_EOL;
		continue;
	}
	else {
		echo "OK", PHP_EOL;
	}
	$l_radiator = array_merge ( $l_radiator, $l_radiator_now );
	$l_radiator ['control'] ['curr_from'] = time ();
	$l_radiator ['control'] ['direction'] = $l_radiator_now ['required'] >= $l_radiator ['comfort'];
	$l_radiator ['control'] ['heating'] = false;

	// Urceni stavu vytapeni podle zdroju
	foreach ( $l_radiator ['poweredby'] as $l_sourceidx ) {
		$l_radiator ['control'] ['heating'] |= $GLOBALS ['sources'] [$l_sourceidx] ['state'];
	}
}
printf ( "End" . PHP_EOL );

radiators_save ();

// ridici cyklus
while ( true ) {
	// Inicializace pres kazdym krokem
	$l_lowbattery = array ();
	$l_day = mktime ( 0, 0, 0 );

	printf ( "\n===============  %s  ================= \n", strftime ( "%X" ) );

	$l_radiators = array_keys ( $GLOBALS ['heating'] ['radiators'] );

	// Nacteni stavu
	radiators_load ();

	$l_radiatorssave = array ();

	// Test, zda-li se maji aktualizovat nastaveni v hlavicich
	foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];
		if ( $l_radiator ['conf'] != 'modified' ) {
			continue;
		}
		// Zapamatovat co ukladam
		$l_radiatorssave [] = $l_idx;

		// Nova konfigurace v JSON, ale neni v hlavicich
		echo "Saving ", $l_radiator ['mac'], '=', $l_radiator ['name'], "...";
		if ( ! cometblue_sendconf ( $l_radiator, PIN ) ) {
			echo "Error", PHP_EOL;
			continue;
		}
		$l_radiator ['conf'] = 'saved';
		echo "OK", PHP_EOL;
		sleep ( 10 );
	}

	// Budu zpracovavat pouze nove ulozene radiatory, ostatni necham
	if ( $l_radiatorssave ) {
		$l_radiators = $l_radiatorssave;
	}

	// Nacteni aktualniho stavu z hlavice a
	printf ( "Reading state of radiators\n" );
	foreach ( $l_radiators as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

		echo $l_radiator ['mac'], '=', $l_radiator ['name'], "...";

		unset ( $l_radiator_now );
		$l_radiator_now = cometblue_receiveconf ( $l_radiator ['mac'], PIN );
		if ( $l_radiator_now === false ) {
			if ( ! testing ) {
				echo "Error", PHP_EOL;
				continue;
			}
			else {
				$l_radiator_now = $l_radiator;
			}
		}
		else {
			echo "OK", PHP_EOL;
		}
		// Doslo ke zmene hodnoty? Poznamenej si cas zmeny
		if ( $l_radiator_now ['current'] != $l_radiator ['current'] ) {
			$l_radiator ['control'] ['curr_from'] = time ();
		}

		// Urceni predchazejiciho stavu zdroje podle nastaveni zdroju
		$l_heating_curr = false;
		foreach ( $l_radiator ['poweredby'] as $l_idx ) {
			unset ( $l_source );
			$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];
			$l_heating_curr |= $l_source ['state'];
		}

		$l_heating = false;

		/* URCENI DALSIHO BEHU, FUZZY CONTROLLER */

		// Meni se smer?
		if ( $l_radiator ['required'] != $l_radiator_now ['required'] ) {
			// Resetuje se delka soucasne hodnoty
			$l_radiator ['control'] ['curr_from'] = time ();
			// Urceni noveho smeru
			$l_radiator ['control'] ['direction'] = $l_radiator_now ['required'] > $l_radiator ['required'];

			// Teplota se zvedla, tak top
			if ( $l_radiator ['control'] ['direction'] ) {
				$l_heating = true;
				$l_radiator ['control'] ['state'] = sprintf ( 'heating-requp-zmena teploty na vyssi %s->%s', $l_radiator ['required'], $l_radiator_now ['required'] );
			}
			else {
				$l_heating = false;
				$l_radiator ['control'] ['state'] = 'cooling-reqdown-zmena teploty na nizsi';
			}
		}
		elseif ( $l_radiator_now ['current'] < $l_radiator_now ['required'] ) {
			$l_heating = true;
			$l_radiator ['control'] ['state'] = 'heating-tempbelow-teplota pod pozadovanou hranici';
		}
		elseif ( $l_radiator ['control'] ['heating'] && $l_radiator_now ['current'] < $l_radiator ['current'] ) {
			$l_heating = false;
			$l_radiator ['control'] ['state'] = 'cooling-on-tempdown-hlavice se zavrela';
		}
		elseif ( $l_radiator ['control'] ['heating'] && (time () - $l_radiator ['control'] ['curr_from']) > HEATING_TIMEOUT ) {
			$l_heating = false;
			$l_radiator ['control'] ['state'] = 'cooling-tempstable-timeout-topi se, ale teplota se nemeni v intervalu, asi je hlavice presne nastavena na pozadovanou teplotu';
		}
		elseif ( ! $l_radiator ['control'] ['heating'] && ! $l_heating_curr && $l_radiator ['control'] ['direction'] && $l_radiator_now ['current'] < $l_radiator ['current'] ) {
			$l_heating = true;
			$l_radiator ['control'] ['state'] = 'heating-requp-tempdown-netopi se, teplota klesla, ale ma se vytapet, tak zase zacinam';
		}
		else {
			$l_heating = $l_radiator ['control'] ['heating'];
			$l_radiator ['control'] ['state'] = 'stable';
		}

		// Logovani statistiky
		if ( $l_heating != $l_radiator ['control'] ['heating'] ) {
			if ( ! $l_heating ) {
				$l_cas = (time () - strtotime ( $l_radiator ['control'] ['runningfrom'] )) / 60;
				$l_radiator ['statistic'] ['day'] [$l_day] += $l_cas;
				$l_radiator ['statistic'] ['summary'] += $l_cas;
				$l_radiator ['control'] ['runningfrom'] = null;
			}
			else {
				$l_radiator ['control'] ['runningfrom'] = strftime ( "%x %X" );
			}
		}

		$l_radiator ['control'] ['heating'] = $l_heating;
		$l_radiator ['required'] = $l_radiator_now ['required'];
		$l_radiator ['previous'] = $l_radiator ['current'];
		$l_radiator ['current'] = $l_radiator_now ['current'];
		$l_radiator ['lastdata'] = $l_radiator_now ['lastdata'];

		if ( $l_radiator_now ['battery'] < BATTERY_LIMIT ) {
			$l_lowbattery [] = $l_radiator ['mac'] . "-" . $l_radiator ['name'] . "-" . $l_radiator_now ['battery'];
		}
	}

	// Urceni co se ma udelat s jakym spinacem
	foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
		unset ( $l_source );
		$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];

		$l_state = false;
		foreach ( $l_source ['controledby'] as $l_idx ) {
			if ( ! isset ( $GLOBALS ['heating'] ['radiators'] [$l_idx] ) ) {
				continue;
			}

			unset ( $l_radiator );
			$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];
			$l_state |= $l_radiator ['control'] ['heating'];
		}
		// Spusteni/ vypnuti kotle
		if ( $l_state ) {
			bt04a_on ( $l_source ['dev'] );
		}
		else {
			bt04a_off ( $l_source ['dev'] );
		}

		if ( $l_state != $l_source ['state'] ) {
			if ( $l_state == false ) {
				$l_cas = (time () - strtotime ( $l_source ['runningfrom'] )) / 60;
				$l_source ['statistic'] ['day'] [$l_day] += $l_cas;
				$l_source ['statistic'] ['summary'] += $l_cas;
				$l_source ['runningfrom'] = null;
			}
			else {
				$l_source ['runningfrom'] = strftime ( "%x %X" );
			}
		}

		// Nastaveni aktualniho stavu
		$l_source ['state'] = $l_state;
	}

	// ZPRAVY
	// Vytvoreni zpravy pro reporty
	$l_message_subjectreport = '';
	$l_message_subject = array ();

	if ( $l_lowbattery ) {
		$l_message_subjectreport .= sprintf ( "Low battery:%s\n\n", implode ( PHP_EOL, $l_lowbattery ) );
	}

	// Zdroje
	unset ( $l_source );
	foreach ( $GLOBALS ['heating'] ['sources'] as $l_source ) {
		$l_message_subjectreport .= control_info_source ( $l_source );
		$l_message_subjectreport .= PHP_EOL . PHP_EOL;

		$l_message_subject [] = $l_source ['state'];
	}

	// Radiatory
	unset ( $l_radiator );
	foreach ( $GLOBALS ['heating'] ['radiators'] as $l_radiator ) {
		$l_message_subjectreport .= control_info_radiator ( $l_radiator );
		$l_message_subjectreport .= PHP_EOL . PHP_EOL;
	}

	// Poslani emailu
	if ( ! testing ) {
		mail ( "root", "HEATING  Report " . implode ( ",", $l_message_subject ), $l_message_subjectreport . ((testing) ? var_export ( $GLOBALS ['heating'], true ) : "") );
	}

	// Vypsani na vystup
	printf ( PHP_EOL . PHP_EOL . $l_message_subjectreport );

	/* URCENI DALSIHO CASU ZPRACOVANI */

	$l_next = time () + 60 * INTERVAL;
	$GLOBALS ['heating'] ['next'] = strftime ( "%x %X", $l_next );

	// Prubezne ulozeni
	radiators_save ();

	printf ( "Next cycle: %s\n", strftime ( "%X", $l_next ) );

	// cekam na dalsi cyklus - bud vypsi doba, nebo se objevi soubor fastfile
	$l_minutes = INTERVAL;
	file_exists ( fastfile ) && unlink ( fastfile );
	while ( ! file_exists ( fastfile ) && $l_minutes > 0 ) {
		sleep ( (testing) ? 30 : 60 );

		$l_minutes --;
	}
	if ( file_exists ( fastfile ) ) {
		printf ( "Fast detected" . PHP_EOL );
	}
}



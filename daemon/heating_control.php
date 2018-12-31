<?php
chdir ( dirname ( __FILE__ ) . '/..' );

require_once 'config.php';
require_once 'inc/bridge.php';
require_once 'inc/radiator.php';
require_once 'inc/source.php';

define("RECONFIGUREINTERVAL", 60);

radiators_load ();

if ( ! $argv [1] ) {
	$l_configfile = 'bridge.json';
}
else {
	$l_configfile = $argv [1];
}

bridge_load ( $l_configfile );
// Pripojuji se na vse
bridgemaster_connect ();

printf ( "\nInicialize state of radiators\n" );
radiators_load ();
foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
	unset ( $l_radiator );
	$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

	if ( isset ( $l_radiator ['control'] ) ) {
		continue;
	}

	$l_radiator ['control'] ['curr_from'] = time ();
	$l_radiator ['control'] ['direction'] = $l_radiator ['required'] > $l_radiator ['night'];
	$l_radiator ['control'] ['heating'] = false;

	// Urceni stavu vytapeni podle zdroju
	foreach ( $l_radiator ['poweredby'] as $l_sourceidx ) {
		$l_radiator ['control'] ['heating'] |= $GLOBALS ['sources'] [$l_sourceidx] ['state'];
	}
}
printf ( "End" . PHP_EOL );

// ridici cyklus
while ( true ) {
	/* URCENI DALSIHO CASU ZPRACOVANI */
	$l_next = time () + 60 * INTERVAL;
	$GLOBALS ['heating'] ['next'] = strftime ( "%x %X", $l_next );
	radiators_save ();
	bridge_publish('synchro', $GLOBALS ['heating'] ['next']);

	// saving timeout
	$l_savinigtime = time () + RECONFIGUREINTERVAL;

	// cekam na dalsi cyklus - bud vypsi doba, nebo se objevi soubor fastfile
	printf ( "Processing MQTT to %s" . PHP_EOL, $GLOBALS ['heating'] ['next'] );
	file_exists ( fastfile ) && unlink ( fastfile );
	while ( time () < $l_next && ! file_exists ( fastfile ) ) {
		$GLOBALS ['bridge'] ['client']->loop ();

		if ( time () > $l_savinigtime ) {
			radiators_load ();

			// Test, zda-li se maji aktualizovat nastaveni v hlavicich
			foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
				unset ( $l_radiator );
				$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];
				if ( $l_radiator ['conf'] != 'modified' ) {
					continue;
				}

				bridge_publish('radiator_reconfigure/' . $l_radiator ['name'], $l_radiator);
				printf ( "Sent new configuration [%s]".PHP_EOL, $l_radiator ['name'] );

				$l_radiator ['conf'] = 'saved';
			}

			radiators_save ();

			$l_savinigtime = time () + RECONFIGUREINTERVAL;
		}

		sleep ( 1 );
	}

	// Nacteni stavu
	radiators_load ();

	// Inicializace pres kazdym krokem
	$l_lowbattery = array ();
	$l_day = mktime ( 0, 0, 0 );

	printf ( "\n===============  %s  ================= \n", strftime ( "%X" ) );

	$l_radiators = array_keys ( $GLOBALS ['heating'] ['radiators'] );

	// Nacteni aktualniho stavu z hlavice a
	printf ( "Controller\n" );
	foreach ( $l_radiators as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

		echo $l_radiator ['mac'], '=', $l_radiator ['name'], PHP_EOL;

		// Doslo ke zmene hodnoty? Poznamenej si cas zmeny
		if ( $l_radiator ['current'] != $l_radiator ['previous'] ) {
			$l_radiator ['control'] ['curr_from'] = time ();
		}

		// Urceni predchazejiciho stavu zdroje podle nastaveni zdroju
		$l_heating_curr = false;
		foreach ( $l_radiator ['poweredby'] as $l_idx ) {
			unset ( $l_source );
			$l_source = & source_getbyname ( $l_idx );
			$l_heating_curr |= $l_source ['state'];
		}

		$l_heating = false;

		/* URCENI DALSIHO BEHU, FUZZY CONTROLLER */

		echo $l_radiator ['required'], $l_radiator ['requiredprevious'], PHP_EOL;

		// Meni se smer?
		if ( $l_radiator ['requiredprevious'] != $l_radiator ['required'] ) {
			// Resetuje se delka soucasne hodnoty
			$l_radiator ['control'] ['curr_from'] = time ();
			// Urceni noveho smeru
			$l_radiator ['control'] ['direction'] = $l_radiator ['required'] > $l_radiator ['requiredprevious'];

			// Teplota se zvedla, tak top
			if ( $l_radiator ['control'] ['direction'] ) {
				$l_heating = true;
				$l_radiator ['control'] ['state'] = sprintf ( 'heating-requp-zmena teploty na vyssi %s->%s', $l_radiator ['requiredprevious'], $l_radiator ['required'] );
			}
			else {
				$l_heating = false;
				$l_radiator ['control'] ['state'] = 'cooling-reqdown-zmena teploty na nizsi';
			}
		}
		elseif ( $l_radiator ['current'] < $l_radiator ['required'] ) {
			$l_heating = true;
			$l_radiator ['control'] ['state'] = 'heating-tempbelow-teplota pod pozadovanou hranici';
		}
		elseif ( $l_radiator ['current'] > $l_radiator ['previous'] && $l_heating_curr ) {
			$l_heating = true;
			$l_radiator ['control'] ['state'] = 'heating-temp raising-topi se';
		}
		elseif ( $l_radiator ['control'] ['heating'] && $l_radiator ['current'] < $l_radiator ['previous'] ) {
			$l_heating = false;
			$l_radiator ['control'] ['state'] = 'cooling-on-tempdown-hlavice se zavrela';
		}
		elseif ( $l_radiator ['control'] ['heating'] && (time () - $l_radiator ['control'] ['curr_from']) > HEATING_TIMEOUT ) {
			$l_heating = false;
			$l_radiator ['control'] ['state'] = 'cooling-tempstable-timeout-topi se, ale teplota se nemeni v intervalu, asi je hlavice presne nastavena na pozadovanou teplotu';
		}
		elseif ( ! $l_radiator ['control'] ['heating'] && ! $l_heating_curr && $l_radiator ['control'] ['direction'] && $l_radiator ['current'] < $l_radiator ['previous'] ) {
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
		$l_radiator ['requiredprevious'] = $l_radiator ['required'];
		$l_radiator ['previous'] = $l_radiator ['current'];

		if ( $l_radiator ['battery'] < BATTERY_LIMIT ) {
			$l_lowbattery [] = $l_radiator ['mac'] . "-" . $l_radiator ['name'] . "-" . $l_radiator ['battery'];
		}
	}

	// Urceni co se ma udelat s jakym spinacem
	foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
		unset ( $l_source );
		$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];

		# urceni noveho stavu
		$l_state = false;
		foreach ( $l_source ['controledby'] as $l_idx ) {
			unset ( $l_radiator );
			$l_radiator = & radiator_getbyname ( $l_idx );
			$l_state |= $l_radiator ['control'] ['heating'];
		}

		// Nastaveni stavu zdroje
		bridge_publish('source_set/' . $l_source ['name'], $l_state);

		// Statisika
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
}

bridge_disconnect ();

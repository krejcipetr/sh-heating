<?php
chdir ( dirname ( __FILE__ ) . '/..' );

require_once 'config.php';
require_once 'inc/bridge.php';
require_once 'inc/radiator.php';
require_once 'inc/source.php';

radiators_load ();

if (! $argv[1]) {
	$l_configfile = 'bridge.json';
}
else {
	$l_configfile = $argv[1];
}

bridge_load ($l_configfile);

// Pripojuji se na vse
bridgemaster_connect ( );

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

		$l_json = json_encode($l_radiator);
		$GLOBALS ['bridge'] ['client']->publish ( $GLOBALS ['bridge'] ['id'] . '/radiator_reconfigure/' . $l_radiator ['name'] , $l_json );
	}

	// Budu zpracovavat pouze nove ulozene radiatory, ostatni necham
	if ( $l_radiatorssave ) {
		$l_radiators = $l_radiatorssave;
	}

	// Nacteni aktualniho stavu z hlavice a
	printf ( "Controller\n" );
	foreach ( $l_radiators as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = & $GLOBALS ['heating'] ['radiators'] [$l_idx];

		echo $l_radiator ['mac'], '=', $l_radiator ['name'];

		// Doslo ke zmene hodnoty? Poznamenej si cas zmeny
		if ( $l_radiator ['current'] != $l_radiator ['previous'] ) {
			$l_radiator ['control'] ['curr_from'] = time ();
		}

		// Urceni predchazejiciho stavu zdroje podle nastaveni zdroju
		$l_heating_curr = false;
		foreach ( $l_radiator ['poweredby'] as $l_idx ) {
			unset ( $l_source );
			$l_source = & source_getbyname($l_idx);
			$l_heating_curr |= $l_source ['state'];
		}

		$l_heating = false;

		/* URCENI DALSIHO BEHU, FUZZY CONTROLLER */

		// Meni se smer?
		if ( $l_radiator ['requiredprevious'] != $l_radiator ['required'] ) {
			// Resetuje se delka soucasne hodnoty
			$l_radiator ['control'] ['curr_from'] = time ();
			// Urceni noveho smeru
			$l_radiator ['control'] ['direction'] = $l_radiator ['required'] > $l_radiator ['requiredprevious'];

			// Teplota se zvedla, tak top
			if ( $l_radiator ['control'] ['direction'] ) {
				$l_heating = true;
				$l_radiator ['control'] ['state'] = sprintf ( 'heating-requp-zmena teploty na vyssi %s->%s', $l_radiator ['requiredprevious'], $l_radiator['required'] );
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
			$l_lowbattery [] = $l_radiator ['mac'] ."-" . $l_radiator ['name'] . "-" . $l_radiator ['battery'];
		}
	}

	// Urceni co se ma udelat s jakym spinacem
	foreach ( array_keys ( $GLOBALS ['heating'] ['sources'] ) as $l_idx ) {
		unset ( $l_source );
		$l_source = & $GLOBALS ['heating'] ['sources'] [$l_idx];

		$l_state = false;
		foreach ( $l_source ['controledby'] as $l_idx ) {
			unset ( $l_radiator );
			$l_radiator = & radiator_getbyname($l_idx);
			$l_state |= $l_radiator ['control'] ['heating'];
		}

		$l_json = json_encode($l_state);
		$GLOBALS ['bridge'] ['client']->publish ( $l_radiator['bridge']. '/source_set/' . $l_source ['name'] , $l_json );


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
	$l_minutes = INTERVAL *60 + time();
	file_exists ( fastfile ) && unlink ( fastfile );
	while ( ! file_exists ( fastfile ) && time() < $l_minutes ) {
		$GLOBALS ['bridge'] ['client']->loop ();
		sleep ( 1 );
	}
	if ( file_exists ( fastfile ) ) {
		printf ( "Fast detected" . PHP_EOL );
	}
}

bridge_disconnect();
<?php
header ( "Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate, post-check=0, pre-check=0", true );
header ( "Pragma: no-cache,no-store", true );
header ( "Expires: -1", true );

chdir ( dirname ( __FILE__ ) . '/..' );

require_once 'config.php';
require_once 'inc/radiator.php';
require_once 'inc/cometblue.php';

session_start ();

radiators_load ();

if ( isset ( $_REQUEST ['refresh'] ) ) {
	touch ( fastfile );
	sleep ( 60 );
	radiators_load ();
}

$l_modes = array ("Radiátory", "Dovolená", "Kotle" );

if ( isset ( $_REQUEST ['mode'] ) ) {
	$_SESSION ['mode'] = intval ( $_REQUEST ['mode'] );
}

if ( ! isset ( $_SESSION ['mode'] ) ) {
	$_SESSION ['mode'] = 0;
}

if ( isset ( $_REQUEST ['radiator'] ) ) {
	$_SESSION ['radiator'] = intval ( $_REQUEST ['radiator'] );
}
if ( ! isset ( $_SESSION ['radiator'] ) ) {
	reset ( $GLOBALS ['heating'] ['radiators'] );
	$_SESSION ['radiator'] = key ( $GLOBALS ['heating'] ['radiators'] );
}

if ( isset ( $_REQUEST ['source'] ) ) {
	$_SESSION ['source'] = intval ( $_REQUEST ['source'] );
}
if ( ! isset ( $_SESSION ['source'] ) ) {
	reset ( $GLOBALS ['heating'] ['sources'] );
	$_SESSION ['source'] = key ( $GLOBALS ['heating'] ['sources'] );
}

// Ma se ulozit dovolena?
if ( isset ( $_REQUEST ['dovolena'] ) ) {
	if ( empty ( $_REQUEST ['from'] ) ) {
		$l_from = time ();
	}
	else {
		$l_from = strtotime ( $_REQUEST ['from'] );
	}
	if ( $l_from === false ) {
		die ( "Chybne od" );
	}
	if ( empty ( $_REQUEST ['to'] ) ) {
		die ( "Chybne do" );
	}
	else {
		$l_to = strtotime ( $_REQUEST ['to'] );
	}
	if ( $l_to === false ) {
		die ( "Chybne do" );
	}

	foreach ( $GLOBALS ['heating'] ['radiators'] as $l_idx => $l_radiator ) {
		$GLOBALS ['heating'] ['radiators'] [$l_idx] ['dovolena'] = array ('from' => strftime ( "%x %X", $l_from ), 'to' => strftime ( "%x %X", $l_to ), "temp" => floatval ( $_REQUEST ["temp"] ) );
		$GLOBALS ['heating'] ['radiators'] [$l_idx] ['conf'] = 'modified';
	}
}
if ( isset ( $_REQUEST ['dovolenadel'] ) ) {
	foreach ( $GLOBALS ['heating'] ['radiators'] as $l_idx => $l_radiator ) {
		$GLOBALS ['heating'] ['radiators'] [$l_idx] ['dovolena'] = null;
		$GLOBALS ['heating'] ['radiators'] [$l_idx] ['conf'] = 'modified';
	}
}

// Ma se ulozit nastaveni radiatoru/hlavice
if ( isset ( $_REQUEST ['radiatorsettings'] ) ) {

	if ( $_SESSION ['radiator'] == - 1 ) {
		$l_radiators = array_keys ( $GLOBALS ['heating'] ['radiators'] );
	}
	else {
		$l_radiators = array ($_SESSION ['radiator'] );
	}

	// Jednotlive radiatory
	foreach ( $l_radiators as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = &  $GLOBALS ['heating'] ['radiators'] [$l_idx];

		foreach ( array ('required', 'night', 'comfort', 'offset' ) as $l_definition ) {
			if ( ! is_numeric ( $_REQUEST [$l_definition] ) ) {
				continue;
			}
			$l_radiator [$l_definition] = floatval ( $_REQUEST [$l_definition] );
		}

		foreach ( array ("pondeli", "utery", "streda", "ctvrtek", "patek", "sobota", "nedele" ) as $l_den ) {
			for ( $l_idx2 = 0; $l_idx2 < 4; $l_idx2 ++ ) {
				if ( ! preg_match ( "/[0-9]{1,2}:[0-9]{1,2}/", $_REQUEST ['from' . $l_den . $l_idx2] ) || ! preg_match ( "/[0-9]{1,2}:[0-9]{1,2}/", $_REQUEST ['to' . $l_den . $l_idx2] ) ) {
					if ( $_SESSION ['radiator'] != - 1 ) {
						unset ( $l_radiator [$l_den] [$l_idx2] );
					}
					continue;
				}
				$l_radiator [$l_den] [$l_idx2] = array ('from' => $_REQUEST ['from' . $l_den . $l_idx2], 'to' => $_REQUEST ['to' . $l_den . $l_idx2] );
			}
		}

		$l_radiator ['conf'] = 'modified';
	}
	unset ( $l_radiator );
}

// Ma se nastaveni zdroje tepla
if ( isset ( $_REQUEST ['sourcesettings'] ) ) {

	// Jednotlive radiatory
	unset ( $l_source );
	$l_source = &  $GLOBALS ['heating'] ['sources'] [$_SESSION ['source']];

	// Nastaveni kdo zapina zdroj
	$l_source ['controledby'] = $_REQUEST ['controled'];

	// Nastaveni kdo vytapi radiator
	foreach ( array_keys ( $GLOBALS ['heating'] ['radiators'] ) as $l_idx ) {
		unset ( $l_radiator );
		$l_radiator = &  $GLOBALS ['heating'] ['radiators'] [$l_idx];

		if ( in_array ( $l_idx, $_REQUEST ['powered'] ) ) {
			! in_array ( $_SESSION ['source'], $l_radiator ['poweredby'] ) && $l_radiator ['poweredby'] [] = $_SESSION ['source'];
		}
		else {
			$l_radiator ['poweredby'] = array_diff ( $l_radiator ['poweredby'], array ($_SESSION ['source'] ) );
		}
	}
	unset ( $l_radiator );
	unset ( $l_source );
}

$l_zmena = false;
foreach ( $GLOBALS ['heating'] ['radiators'] as $l_radiator ) {
	$l_zmena |= $l_radiator ['conf'] == 'modified';
}
if ( $l_zmena ) {
	radiators_save ();
	touch ( fastfile );
}
else {
	semup ();
}

$l_zmeny = array ();
foreach ( $GLOBALS ['heating'] ['radiators'] as $l_radiator ) {
	if ( $l_radiator ['conf'] != 'modified' ) {
		continue;
	}
	$l_zmeny [] = $l_radiator ['name'];
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta title="Konfigurace vytápění">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="utf-8">
<link rel="stylesheet" type="text/css" href="heating.css" />
<meta http-equiv="refresh"
	content="<?php  strtotime($GLOBALS['heating']['next'])-time() + 10;?>; url=<?php basename(__FILE__)?>">
</head>
<body style=""><?php
// generace stranky

// Lista s mody
echo '<div class="modes">';
foreach ( $l_modes as $l_idx => $l_mode ) {
	echo '<input type="button" value="', htmlspecialchars ( $l_mode ), '" onclick="document.location.href=\'?mode=', $l_idx, '\';">';
}
echo '</div>';

// Informace o zmenach k nahrani do hlavice
echo '<div class="queue">Změny na nahrání:';

if ( $l_zmeny ) {
	echo implode ( "&nbsp;,&nbsp;", $l_zmeny );
}
else {
	echo " žádné";
}
echo "</div>";
// Vlastni zobrazeni
echo '<form action="#" method="post" name="data">';
switch ( $_SESSION ['mode'] ) {
	// KOTLE
	case 2 :
		echo '<div class="pokoje">';
		unset ( $l_source );
		foreach ( $GLOBALS ['heating'] ['sources'] as $l_idx => $l_source ) {
			echo '<input type="button" value="', htmlspecialchars ( $l_source ['name'] ), '" onclick="document.location.href=\'?source=', $l_idx, '\';">';
		}
		echo '</div>';
		echo '<div class="radiatorconfig">';
		$l_source = $GLOBALS ['heating'] ['sources'] [$_SESSION ['source']];
		echo '<div style="text-align:center;"><h1>', htmlspecialchars ( $l_source ['name'] ), '</h1></div>';
		echo '<div class="panel setting">';
		echo '<div class="label">Zdroj pro</div>';
		foreach ( $GLOBALS ['heating'] ['radiators'] as $l_idx => $l_radiator ) {
			echo '<label>', htmlspecialchars ( $l_radiator ['name'] ), '</label>';
			echo '<input type="checkbox" value="',$l_idx,'" name="powered[]" ', ((in_array ( $_SESSION ['source'], $l_radiator ['poweredby'] )) ? 'checked' : ''), '>';
			echo '<br>';
		}
		echo '</div>';
		echo '<div class="panel setting">';
		echo '<div class="label">Jsem ovládaný</div>';
		foreach ( $GLOBALS ['heating'] ['radiators'] as $l_idx => $l_radiator ) {
			echo '<label>', htmlspecialchars ( $l_radiator ['name'] ), '</label>';
			echo '<input type="checkbox" value="',$l_idx,'" name="controled[]" ', ((in_array ( $l_idx, $l_source ['controledby'] )) ? 'checked' : ''), '>';
			echo '<br>';
		}
		echo '</div>';
		echo '<div style="text-align:center;"><input type="submit" name="sourcesettings" value="Uložit"></div>';
		echo '</div>';

		break;

	// Dovolena
	case 1 :
		// Z prniho radiatoru nacti dovolenou
		echo '<div class="panel dovolena"><div class="label">Dovolená</div>';
		echo '<input type="text" name="from" value="', $l_radiator ['dovolena'] ['from'], '">';
		echo '&nbsp;-&nbsp;';
		echo '<input type="text" name="to" value="', $l_radiator ['dovolena'] ['to'], '">';
		echo '<input type="text" name="temp" value="', $l_radiator ['dovolena'] ['temp'], '">';
		echo '<br>';
		echo '<input type="submit" name="dovolena" value="Uložit">';
		echo '<input type="submit" name="dovolenadel" value="Vymazat">';
		echo '</div>';
		break;

	// Tydenni planovani
	case 0 :
		echo '<div class="pokoje">';
		foreach ( $GLOBALS ['heating'] ['radiators'] as $l_idx => $l_radiator ) {
			echo '<input type="button" value="', htmlspecialchars ( $l_radiator ['name'] ), '" onclick="document.location.href=\'?radiator=', $l_idx, '\';">';
		}
		echo '<input type="button" value="Všechny" onclick="document.location.href=\'?radiator=-1\';">';
		echo '</div>';

		echo '<div class="radiatorconfig">';

		if ( $_SESSION ['radiator'] != - 1 ) {
			$l_radiator = $GLOBALS ['heating'] ['radiators'] [$_SESSION ['radiator']];
			echo '<div style="text-align:center;"><h1>', htmlspecialchars ( $l_radiator ['name'] ), '</h1></div>';
		}
		else {
			$l_radiator = array ();
		}

		?>
		<div class="panel setting">
		<div class="label">Teploty</div>
		<label>Nastavené</label> <input type="text" name="required"
			value="<?php echo $l_radiator["required"];?>"> <br> <label>Noční</label>
		<input type="text" name="night"
			value="<?php echo $l_radiator["night"];?>"> <br> <label>Denní</label>
		<input type="text" name="comfort"
			value="<?php echo $l_radiator["comfort"];?>"> <br> <label>Korekce</label>
		<input type="text" name="offset"
			value="<?php echo $l_radiator["offset"];?>">
	</div>
<?php
		foreach ( array ("pondeli" => "Pondělí", "utery" => "Útery", "streda" => "Středa", "ctvrtek" => "Čtvrtek", "patek" => "Pátek", "sobota" => "Sobota", "nedele" => "Neděle" ) as $l_den => $l_denstring ) {
			echo '<div class="panel programator_den">';
			echo '<div class="label">', htmlspecialchars ( $l_denstring ), "</div>";
			$l_idx2 = - 1;
			foreach ( $l_radiator [$l_den] as $l_idx2 => $l_interval ) {
				echo '<input type="text" name="from', $l_den, $l_idx2, '" value="', $l_interval ["from"], '">';
				echo '&nbsp;-&nbsp;';
				echo '<input type="text" name="to', $l_den, $l_idx2, '" value="', $l_interval ["to"], '">';
				echo "<br>";
			}
			for ( $l_idx2 ++; $l_idx2 < 4; $l_idx2 ++ ) {
				echo '<input type="text" name="from', $l_den, $l_idx2, '" value="">';
				echo '&nbsp;-&nbsp;';
				echo '<input type="text" name="to', $l_den, $l_idx2, '" value="">';
				echo "<br>";
			}
			echo '</div>';
		}

		echo '<div style="text-align:center;"><input type="submit" name="radiatorsettings" value="Uložit"></div>';
		echo '</div>';

		break;
}

echo "</form>";

?>
<div>
<?php echo strftime("%x %X"), " => ", $GLOBALS['heating']['next'];?>
&nbsp;<a href="?refresh">obnovit</a>
	</div>
</body>
</html>

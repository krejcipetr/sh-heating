<?php
header("Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate, post-check=0, pre-check=0", true);
header("Pragma: no-cache,no-store", true);
header("Expires: -1", true);

chdir(dirname(__FILE__) . '/..');

$l_day = mktime(0, 0, 0);

require_once 'state/config.php';
require_once 'inc/radiator.php';
require_once 'inc/cometblue.php';

session_start();

radiators_load();

if (isset($_REQUEST['changestate'])) {
    $l_radiator = & $GLOBALS['heating']['radiators'][$_REQUEST['changestate']];
    $l_radiator['control']['direction'] = $_REQUEST['newstate'];
    $l_radiator['required'] = ($l_radiator['control']['direction']) ? $l_radiator['comfort'] : $l_radiator['night'];

    if ($l_radiator['control']['direction'] != $l_radiator['control']['heating']) {
    	if ( $l_radiator['control']['direction'] ) {
    		$l_radiator['control']['runningfrom'] = strftime ( "%x %X" );
    	}
    	else {
    		$l_radiator ['statistic'] ['day'] [$l_day] += (time () - strtotime ( $l_radiator ['control'] ['runningfrom'] )) / 60;
    		$l_radiator ['statistic'] ['summary'] += (time () - strtotime ( $l_radiator ['control'] ['runningfrom'] )) / 60;
    		$l_radiator ['control'] ['runningfrom'] = null;
    	}
    }

    $l_radiator['control']['heating'] = $l_radiator['control']['direction'];
    $l_radiator['conf'] = 'modified';
    unset($l_radiator);
    radiators_save();
    touch(fastfile);
} else {
    semup();
}

if (isset($_REQUEST['refresh'])) {
    touch(fastfile);
    sleep(60);
    radiators_load();
	semup ();
}

if ( isset ( $_REQUEST ['changemode'] ) ) {
	$_SESSION['viewmode']['radiator'][$_REQUEST ['changemode']] = $_REQUEST['newmode'];
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="utf-8">
<link rel="stylesheet" type="text/css" href="heating.css" />
<meta http-equiv="refresh"
	content="<?php

echo strtotime($GLOBALS['heating']['next']) - time() + 10;
?>; url=heating.php">

</head>
<body class="heatinginfo">
<?php
// KOTEL
foreach ($GLOBALS['heating']['sources'] as $l_idx => $l_source) {
    ?>
		<div class="panel source">
		<div class="label"><?php

    echo htmlspecialchars($l_source['name'])?></div>
		<img class="heating"
			src="<?php

    echo ($l_source['state']) ? 'heating.png' : 'noheating.png';
    ?>"
			title="topeni">
		<!-- Napajene radiatory -->
		<div class="for">
		<?php
    foreach ($l_source['for'] as $l_for) {
        echo htmlspecialchars($GLOBALS['heating']['radiators'][$l_for]['name']), "<br>";
    }
    ?>
		</div>
    <?php
    // Vypocteni aktualne beziciho casu
    $l_cas = ($l_source['runningfrom'] != null) ? intval((time() - strtotime($l_source['runningfrom'])) / 60) : 0;
    ?>
    <!-- Delka behu zdroje -->
		<div class="runningtime"><?php

    echo $l_source['runningfrom'];
    ?><br><?php

    if ($l_cas) {
        echo $l_cas, "&nbsp;min";
    }
    ?><br>
			<?php
    printf("%d&nbsp;min", floatval(($l_source['statistic']['day'][$l_day] + $l_cas)));
    ?><br>
			<?php

    printf("%.1f&nbsp;hod", floatval(($l_source['statistic']['summary'] + $l_cas) / 60));
    ?></div>
	</div>
<?php
}
// RADIATORY
foreach ($GLOBALS['heating']['radiators'] as $l_idx => $l_radiator) {
    ?>
		<div
		class="panel radiator <?php echo $l_radiator['control']['direction']?"heating":"cooling";?>">
		<div class="label"><?php echo htmlspecialchars($l_radiator['name'])?></div>
		<img class="heating"
			src="<?php echo ($l_radiator['control']["heating"])?'heating.png':'noheating.png';?>"
			title="topeni" onclick="document.location.href='?changestate=<?php echo $l_idx;?>&newstate=<?php echo (! $l_radiator['control']['direction'])?1:0;?>';">
		<span class="aktualni"><?php printf("%.1f",$l_radiator["current"]);?></span>

<?php if ( $_SESSION['viewmode']['radiator'][$l_idx]) {
			$l_day = mktime(0,0,0);
			$l_cas = ($l_radiator['control'] ['runningfrom'] != null) ? intval ( (time () - strtotime ( $l_radiator ['control']['runningfrom'] )) / 60 ) : 0;
			echo '<span class="required"';
			echo 'onclick="document.location.href=\'?changemode=',$l_idx,'&newmode=0\';"';
			echo ' >';
			printf ("%.1f&nbsp;min", floatval(($l_radiator ['statistic']['day'][$l_day]+ $l_cas)));
			echo '&nbsp;,&nbsp;';
			printf ("%.1f&nbsp;hod", floatval(($l_radiator ['statistic']['summary'] + $l_cas)/60));
			echo '</span>';
    }
	else { ?>
		<span class="required" onclick="document.location.href='?changemode=<?php echo $l_idx;?>&newmode=1';"><?php printf("%s", $l_radiator["required"]);?> °C</span>
<?php }?>

	</div>
<?php
}

?>
<div>
<?php

echo strftime("%x %X"), " => ", $GLOBALS['heating']['next'];
?>
&nbsp;<a href="?refresh">Obnovit</a>
	</div>
</body>
</html>
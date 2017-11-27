<?php

require_once 'sem.php';

/**
 * Informace o radiatoru behem rizeni
 *
 * @param string $a_radiator
 * @return string
 */
function control_info_radiator ($a_radiator)
{
    return sprintf("Name: %s" . PHP_EOL, $a_radiator['name']) .
             sprintf("Current temp: %.1f" . PHP_EOL, $a_radiator['current']) .
             sprintf("Required temp: %.1f" . PHP_EOL, $a_radiator['required']) . sprintf(
                    "Direction: %d" . PHP_EOL,
                    $a_radiator['control']['direction']) .
             sprintf("Heating: %d" . PHP_EOL, $a_radiator['control']['heating']) .
             sprintf("State: %s" . PHP_EOL, $a_radiator['control']['state']);
}

function control_info_source ($a_source)
{
    $l_cas = ($a_source['runningfrom']) ? ((time() -
             strtotime($a_source['runningfrom'])) / 60) : 0;
    return sprintf("Name: %s" . PHP_EOL, $a_source['name']) .
             sprintf("State: %b" . PHP_EOL, $a_source['state']) . sprintf(
                    "Summary: %.1f hours" . PHP_EOL,
                    floatval(($a_source['summary'] + $l_cas) / 60)) .
             sprintf("Running from: %s" . PHP_EOL, $a_source['runningfrom']) .
             sprintf("Time: %.1f min" . PHP_EOL, floatval($l_cas));
}


/**
 * Ulozi globalni promenou na FS
 */
function radiators_save ()
{
    if (testing) {
        return;
    }
    copy('radiators.json', $GLOBALS['logs'] . 'radiators_' . time() . '.json');
    file_put_contents('radiators.json', json_encode($GLOBALS['heating']));
    
    semup();
}

function radiators_load ()
{
    semdown();
    
    $GLOBALS['heating'] = json_decode(file_get_contents('radiators.json'), true);
    
    if (testing) {
        $GLOBALS['heating']['radiators'] = array(
                reset($GLOBALS['heating']['radiators'])
        );
    }
}

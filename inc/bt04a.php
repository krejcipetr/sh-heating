<?php

/**
 * Zjisti stav spinace na odpovidajicim zarizeni
 * 
 * @param string $a_device
 * @return boolean
 */
function bt04a_getstate($a_device)
{
    if (testing) {
        return true;
    }
    
    $l_rfcomm = fopen($a_device, "wb+");
    if ($l_rfcomm === false) {
        die("Nepodarilo se otevrit spojeni na kotel");
    }
    fwrite($l_rfcomm, base64_decode("r/0H3w=="));
    fflush($l_rfcomm);
    $l_ret = fread($l_rfcomm, 1);
    fclose($l_rfcomm);
    return (ord($l_ret[0]) == 1);
}

/**
 * Zapne spinac na zarizeni
 *
 * @param string $a_device
 */
function bt04a_on($a_device)
{
    if (testing) {
        return;
    }
    $l_rfcomm = fopen($a_device, "wb+");
    if ($l_rfcomm === false) {
        die("Nepodarilo se otevrit spojeni na kotel");
    }
    fwrite($l_rfcomm, base64_decode("r/0A3w=="));
    fflush($l_rfcomm);
    fclose($l_rfcomm);
}

/**
 * Vyplne spinac na zarizeni
 *
 * @param string $a_device
 */
function bt04a_off($a_device)
{
    if (testing) {
        return;
    }
    $l_rfcomm = fopen($a_device, "wb+");
    if ($l_rfcomm === false) {
        die("Nepodarilo se otevrit spojeni na kotel");
    }
    fwrite($l_rfcomm, base64_decode("r/0B3w=="));
    fflush($l_rfcomm);
    fclose($l_rfcomm);
}

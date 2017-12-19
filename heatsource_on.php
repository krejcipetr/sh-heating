<?php
chdir(dirname(__FILE__));

require_once 'state/config.php';
require_once 'inc/bt04a.php';

foreach ($GLOBALS['heating']['sources'] as $l_source) {
    bt04a_on($l_source['dev']);
    sleep(10);
    printf("%s=%s" . PHP_EOL, $l_source['name'],
            (bt04a_getstate($l_source['dev'])) ? "On" : "Off");
}

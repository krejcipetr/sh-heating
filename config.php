<?php
define("testing", true);

define("INTERVAL", (testing) ? 0.3 : 10);
define("PIN", "0 0 0 0");
define("BATTERY_LIMIT", 10);
define("HEATING_TIMEOUT", 15 * 60);
define("fastfile", "/tmp/heatingfast");
define("delaydisplay", 2);

date_default_timezone_set("Europe/Prague");

$GLOBALS['logs'] = realpath(dirname(__FILE__)) . "/state/";


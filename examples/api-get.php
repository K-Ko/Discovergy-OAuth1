#!/usr/bin/env php
<?php
/**
 *
 */
ini_set('display_errors', 1);
error_reporting(-1);

date_default_timezone_set('Europe/Berlin');

/**
 * Load classes
 */
require __DIR__ . '/../src/OAuth1/Session.php';
require __DIR__ . '/../src/Discovergy/API1.php';

if ($argc < 4) {
    die(
        PHP_EOL .
        'Missing paramters, at least' . PHP_EOL . PHP_EOL .
        '# ' . $argv[0] . ' <identifier> <secret> <endpoint>' . PHP_EOL
    );
}

$identifier = $argv[1];
$secret     = $argv[2];
$endpoint   = $argv[3];
$args       = array_slice($argv, 4);

$params = [];

// Prepare STDERR output stream
$fh = fopen('php://stderr', 'a');

foreach ($args as $arg) {
    @list($param, $value) = explode('=', $arg, 2);
    if ($param == 'from' || $param == 'to') {
        if (!is_numeric($value)) {
            fwrite($fh, "::: PARAM: $param: $value > ");
            $value = strtotime($value) * 1000;
            $d = date('r', $value / 1000);
            fwrite($fh, "$value ($d)\n");
        }
    }
    $params[$param] = $value;
}

try {
    $api = new Discovergy\API1('kko-discovergy-oauth1', $identifier, $secret);
    $api->init();
    fwrite($fh, "::: got a valid session\n");
} catch (Exception $e) {
    fwrite($fh, "::: ERROR: " . $e->getMessage());
    exit;
}

fwrite($fh, "::: Fetch endpoint /$endpoint ...\n");

$res = $api->get($endpoint, $params);

if ($res) {
    echo json_encode($res);
} else {
    fwrite($fh, "::: ERROR\n");
    fwrite($fh, implode(PHP_EOL, $api::$session::$debug));
}

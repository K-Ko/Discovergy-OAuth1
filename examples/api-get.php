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
require __DIR__ . '/../src/Inexogy/API1.php';

if ($argc < 4) {
    die(PHP_EOL . 'Missing paramters, at least' . PHP_EOL . PHP_EOL .
        '# ' . $argv[0] . ' <identifier> <secret> <endpoint>' . PHP_EOL);
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

$cache = __DIR__ . '/../.cache';

try {
    $session = new OAuth1\Session('https://api.inexogy.com/public/v1');
    $session->setCache($cache)->setTTL(3600)->authorize('kko-inexogy-oauth1', $identifier, $secret);

    fwrite($fh, "::: Got a valid OAuth1 session instance\n");

    $api = new Inexogy\API1($session);
    $api->setCache($cache)->setTTL(3600);

    fwrite($fh, "::: Got a valid API instance\n");
} catch (Exception $e) {
    fwrite($fh, "::: ERROR: " . $e->getMessage());
    exit;
}

fwrite($fh, "::: Fetch endpoint /$endpoint ...\n");

$res = $api->get($endpoint, $params);

if ($res) {
    echo "\nDEBUG:\n\n", json_encode($api->getSession()->debug, JSON_PRETTY_PRINT);
    echo "\n\nRESULT:\n\n", json_encode($res, JSON_PRETTY_PRINT);
} else {
    fwrite($fh, "::: ERROR\n");
    fwrite($fh, implode(PHP_EOL, $api->getSession()->debug));
}

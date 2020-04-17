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

$client     = 'discovergy-oauth1-example';
$identifier = $argv[1];
$secret     = $argv[2];
$endpoint   = $argv[3];
$args       = array_slice($argv, 4);

$params = [];

foreach ($args as $arg) {
    @list($param, $value) = explode('=', $arg, 2);
    $params[$param] = $value;
}

try {
    $api = new KKo\Discovergy\API1($client, $identifier, $secret);
} catch (Exception $e) {
    die($e->getMessage());
}

$fh = fopen('php://stderr', 'a');
fwrite($fh, "::: Ok, have a valid session\n");
fwrite($fh, "::: Fetch endpoint /$endpoint ...\n");

$res = $api->get($endpoint, $params);

if ($res) {
    die(json_encode($res));
}

fwrite($fh, "::: ERROR\n");
fwrite($fh, implode(PHP_EOL, $api::$session::$debug));

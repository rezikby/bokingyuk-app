<?php
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline' https://snap-assets.al-pc-id-b.cdn.gtflabs.io https://api.sandbox.midtrans.com https://pay.google.com https://gwk.gopayapi.com https://app.sandbox.midtrans.com;");
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());

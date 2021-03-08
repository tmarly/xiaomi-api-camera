<?php
require __DIR__ . '/vendor/autoload.php';

use Xiaomi\Devices\Camera;
use Xiaomi\Xiaomi;

// Login & pass used for Xiaomi account
$login = "user@email.com";
$password = "MyPassword";

// Authenticate
$xiaomi = new Xiaomi();
$xiaomi->auth($login, $password);

// Get all camera devices
$cameras = $xiaomi->getCameras();

// For each camera ...
/** @var Camera $camera */
foreach($cameras as $camera) {

    // Display settings, so you will be able to see availble settings
    $params = $camera->getParams();
    print_r($params);

    // Enable the camera
    $camera->setParam("power", "on");
    echo "Camera " . $camera->getName() . " powered on !\n";
}

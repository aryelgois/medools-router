<?php

// debug
ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

// autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Medools
aryelgois\Medools\MedooConnection::loadConfig(__DIR__ . '/../config/medools.php');

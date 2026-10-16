<?php

$localConfig = __DIR__ . '/database.local.php';

if (is_file($localConfig)) {
    require_once $localConfig;
}

// Defaults suit a local XAMPP setup. Override them in config/database.local.php.
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'studypath_ai');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

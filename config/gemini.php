<?php

$localConfig = __DIR__ . '/gemini.local.php';

if (is_file($localConfig)) {
    require_once $localConfig;
}

function gemini_is_configured()
{
    return defined('GEMINI_API_KEY')
        && trim((string)GEMINI_API_KEY) !== '';
}

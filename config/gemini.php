<?php

$localConfig = __DIR__ . '/gemini.local.php';

if (is_file($localConfig)) {
    require_once $localConfig;
}

/** Report whether a non-empty Gemini API key is available to the application. */
function gemini_is_configured()
{
    return defined('GEMINI_API_KEY')
        && trim((string)GEMINI_API_KEY) !== '';
}

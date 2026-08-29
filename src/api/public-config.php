<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

json_response([
    'ok' => true,
    'demoMode' => DEMO_MODE,
    'googleEnabled' => GOOGLE_CLIENT_ID !== '',
    'googleClientId' => GOOGLE_CLIENT_ID,
    'currency' => CURRENCY,
    'siteName' => SITE_NAME,
]);

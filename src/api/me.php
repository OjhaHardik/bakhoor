<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

json_response(['ok' => true, 'user' => current_user()]);

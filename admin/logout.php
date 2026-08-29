<?php
require_once __DIR__ . '/../includes/auth.php';

log_out_user();
header('Location: login.php');

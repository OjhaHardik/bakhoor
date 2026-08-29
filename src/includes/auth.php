<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $stmt = db()->prepare('SELECT id, name, email, phone, is_admin, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    $cached = $user;
    return $user;
}

function log_in_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function log_out_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        json_error('You need to be signed in.', 401);
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user || !$user['is_admin']) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

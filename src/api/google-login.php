<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

if (GOOGLE_CLIENT_ID === '') {
    json_error('Google sign-in is not configured yet.', 503);
}

$body = read_json_body();
$credential = (string)($body['credential'] ?? '');

if ($credential === '') {
    json_error('Missing Google credential.');
}

// Verify the ID token with Google's tokeninfo endpoint (no server-side secret needed).
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$response = @file_get_contents($verifyUrl);

if ($response === false) {
    json_error('Could not verify Google sign-in right now. Try again.', 502);
}

$payload = json_decode($response, true);

if (!is_array($payload) || ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    json_error('Google sign-in verification failed.', 401);
}

if (($payload['email_verified'] ?? 'false') !== 'true') {
    json_error('Your Google email is not verified.', 401);
}

$googleId = (string)$payload['sub'];
$email = strtolower((string)$payload['email']);
$name = (string)($payload['name'] ?? $email);

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE google_id = ? OR email = ?');
$stmt->execute([$googleId, $email]);
$user = $stmt->fetch();

if ($user) {
    $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?')->execute([$googleId, $user['id']]);
    $userId = (int)$user['id'];
} else {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, google_id) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $googleId]);
    $userId = (int)$pdo->lastInsertId();
}

log_in_user($userId);

json_response(['ok' => true, 'user' => current_user()]);

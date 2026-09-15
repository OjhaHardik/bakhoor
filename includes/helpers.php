<?php

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['ok' => false, 'error' => $message], $status);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function rupees(int $paise): string
{
    return number_format($paise / 100, 2);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Next sequential invoice number for the current year, e.g. BAB26001,
// BAB26002 ... resetting to 001 each new year. Call inside the same
// transaction as the order insert so the row lock (FOR UPDATE) covers
// the read-then-write.
function next_invoice_number(PDO $pdo): string
{
    $prefix = 'BAB' . date('y');

    $stmt = $pdo->prepare(
        'SELECT invoice_number FROM orders WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    $seq = $last ? ((int)substr((string)$last, strlen($prefix)) + 1) : 1;

    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

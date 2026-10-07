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

// Converts a rupee amount (0-99,99,99,999) to words using the Indian
// numbering system (lakh/crore). Used for the invoice's amount-in-words line.
function number_to_words_indian(int $num): string
{
    if ($num === 0) {
        return 'Zero';
    }

    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $twoDigits = function (int $n) use ($ones, $tens): string {
        if ($n < 20) {
            return $ones[$n];
        }
        return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    };

    $threeDigits = function (int $n) use ($twoDigits, $ones): string {
        $str = '';
        if ($n >= 100) {
            $str .= $ones[intdiv($n, 100)] . ' Hundred';
            $n %= 100;
            if ($n > 0) {
                $str .= ' ';
            }
        }
        if ($n > 0) {
            $str .= $twoDigits($n);
        }
        return $str;
    };

    $crore = intdiv($num, 10000000);
    $num %= 10000000;
    $lakh = intdiv($num, 100000);
    $num %= 100000;
    $thousand = intdiv($num, 1000);
    $hundred = $num % 1000;

    $parts = [];
    if ($crore > 0) {
        $parts[] = $threeDigits($crore) . ' Crore';
    }
    if ($lakh > 0) {
        $parts[] = $threeDigits($lakh) . ' Lakh';
    }
    if ($thousand > 0) {
        $parts[] = $threeDigits($thousand) . ' Thousand';
    }
    if ($hundred > 0) {
        $parts[] = $threeDigits($hundred);
    }

    return implode(' ', $parts);
}

// "Seven Hundred and Thirty Rupees and Fifty Paise Only" style string for
// the invoice footer, built from a total in paise.
function amount_in_words(int $paise): string
{
    $rupees = intdiv($paise, 100);
    $paisePart = $paise % 100;

    $result = number_to_words_indian($rupees) . ($rupees === 1 ? ' Rupee' : ' Rupees');
    if ($paisePart > 0) {
        $result .= ' and ' . number_to_words_indian($paisePart) . ' Paise';
    }

    return $result . ' Only';
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

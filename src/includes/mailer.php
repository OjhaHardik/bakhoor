<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Sends an HTML email about an order and logs it to order_emails.
 * Returns ['ok' => bool, 'status' => 'sent'|'demo'|'failed', 'error' => ?string].
 */
function send_order_email(PDO $pdo, int $orderId, string $subject, string $bodyHtml): array
{
    $stmt = $pdo->prepare(
        'SELECT o.id, o.guest_name, o.guest_email, u.name AS account_name, u.email AS account_email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['ok' => false, 'status' => 'failed', 'error' => 'Order not found.'];
    }

    $toEmail = $order['account_email'] ?? $order['guest_email'];
    $toName = $order['account_name'] ?? $order['guest_name'];

    if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'status' => 'failed', 'error' => 'No valid email address on this order.'];
    }

    if (MAIL_DEMO_MODE) {
        log_order_email($pdo, $orderId, $toEmail, $subject, $bodyHtml, 'demo', null);
        return ['ok' => true, 'status' => 'demo'];
    }

    try {
        smtp_send($toEmail, $toName, $subject, $bodyHtml);
        log_order_email($pdo, $orderId, $toEmail, $subject, $bodyHtml, 'sent', null);
        return ['ok' => true, 'status' => 'sent'];
    } catch (Throwable $e) {
        log_order_email($pdo, $orderId, $toEmail, $subject, $bodyHtml, 'failed', $e->getMessage());
        return ['ok' => false, 'status' => 'failed', 'error' => $e->getMessage()];
    }
}

function log_order_email(PDO $pdo, int $orderId, string $toEmail, string $subject, string $body, string $status, ?string $error): void
{
    $pdo->prepare(
        'INSERT INTO order_emails (order_id, sent_to, subject, body, status, error) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$orderId, $toEmail, $subject, $body, $status, $error]);
}

/**
 * Minimal SMTP client — AUTH LOGIN over STARTTLS (587) or implicit TLS (465).
 * No external dependencies, since the rest of this codebase is dependency-free too.
 */
function smtp_send(string $toEmail, ?string $toName, string $subject, string $bodyHtml): void
{
    $useTls = (int)SMTP_PORT === 465;
    $host = ($useTls ? 'ssl://' : '') . SMTP_HOST;

    $conn = @stream_socket_client($host . ':' . SMTP_PORT, $errno, $errstr, 15);
    if (!$conn) {
        throw new RuntimeException("Could not connect to mail server: $errstr");
    }

    $expect = function (int $code) use ($conn) {
        $line = '';
        do {
            $line = fgets($conn, 515);
            if ($line === false) {
                throw new RuntimeException('Mail server closed the connection unexpectedly.');
            }
        } while (isset($line[3]) && $line[3] === '-');

        if ((int)substr($line, 0, 3) !== $code) {
            throw new RuntimeException("Mail server error: " . trim($line));
        }
        return $line;
    };

    $send = function (string $line) use ($conn) {
        fwrite($conn, $line . "\r\n");
    };

    $ehloHost = strpos(SMTP_FROM_EMAIL, '@') !== false ? substr(strrchr(SMTP_FROM_EMAIL, '@'), 1) : 'localhost';

    $expect(220);
    $send('EHLO ' . $ehloHost);
    $expect(250);

    if (!$useTls) {
        $send('STARTTLS');
        $expect(220);
        if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not start TLS with the mail server.');
        }
        $send('EHLO localhost');
        $expect(250);
    }

    $send('AUTH LOGIN');
    $expect(334);
    $send(base64_encode(SMTP_USER));
    $expect(334);
    $send(base64_encode(SMTP_PASS));
    $expect(235);

    $send('MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
    $expect(250);
    $send('RCPT TO:<' . $toEmail . '>');
    $expect(250);
    $send('DATA');
    $expect(354);

    $fromHeader = mime_header_encode(SMTP_FROM_NAME) . ' <' . SMTP_FROM_EMAIL . '>';
    $toHeader = $toName ? mime_header_encode($toName) . ' <' . $toEmail . '>' : $toEmail;

    $headers = [
        'From: ' . $fromHeader,
        'To: ' . $toHeader,
        'Subject: ' . mime_header_encode($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Date: ' . date('r'),
    ];

    $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\r\n.", "\r\n..", $bodyHtml) . "\r\n.";
    $send($data);
    $expect(250);

    $send('QUIT');
    fclose($conn);
}

function mime_header_encode(string $text): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$orderId = (int)($body['orderId'] ?? 0);
$razorpayOrderId = (string)($body['razorpayOrderId'] ?? '');
$razorpayPaymentId = (string)($body['razorpayPaymentId'] ?? '');
$razorpaySignature = (string)($body['razorpaySignature'] ?? '');

$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND razorpay_order_id = ?');
$stmt->execute([$orderId, $razorpayOrderId]);
$order = $stmt->fetch();

if (!$order) {
    json_error('Order not found.', 404);
}

if ($order['status'] === 'paid') {
    json_response(['ok' => true, 'orderId' => $orderId, 'alreadyPaid' => true]);
}

if ($order['is_demo']) {
    $paymentId = $razorpayPaymentId !== '' ? $razorpayPaymentId : 'demo_pay_' . bin2hex(random_bytes(8));
} else {
    $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
    if (!hash_equals($expectedSignature, $razorpaySignature)) {
        json_error('Payment verification failed.', 400);
    }
    $paymentId = $razorpayPaymentId;
}

$pdo->prepare('UPDATE orders SET status = "paid", razorpay_payment_id = ? WHERE id = ?')
    ->execute([$paymentId, $orderId]);

$items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
$items->execute([$orderId]);
$decrementStock = $pdo->prepare('UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?');
foreach ($items->fetchAll() as $item) {
    if ($item['product_id'] !== null) {
        $decrementStock->execute([(int)$item['quantity'], (int)$item['product_id']]);
    }
}

$lineItems = $pdo->prepare('SELECT product_name, unit_price_paise, quantity FROM order_items WHERE order_id = ?');
$lineItems->execute([$orderId]);

$rows = '';
foreach ($lineItems->fetchAll() as $item) {
    $lineTotal = rupees((int)$item['unit_price_paise'] * (int)$item['quantity']);
    $rows .= '<tr><td style="padding:6px 0;">' . (int)$item['quantity'] . ' × ' . htmlspecialchars($item['product_name'])
        . '</td><td style="padding:6px 0; text-align:right;">₹' . $lineTotal . '</td></tr>';
}

$emailBody = '<div style="font-family:Arial,sans-serif; color:#1e1e1e; max-width:480px;">'
    . '<h2 style="color:#0e2b03; font-weight:600;">Thank you for your order</h2>'
    . '<p>Hi ' . htmlspecialchars($order['guest_name']) . ', we\'ve received your payment for order #' . $orderId . '.</p>'
    . '<table style="width:100%; border-collapse:collapse; margin:16px 0;">' . $rows . '</table>'
    . '<p><strong>Total: ₹' . rupees((int)$order['total_paise']) . '</strong></p>'
    . '<p>Shipping to: ' . htmlspecialchars($order['shipping_address']) . ', '
    . htmlspecialchars($order['shipping_city']) . ', ' . htmlspecialchars($order['shipping_state'])
    . ' ' . htmlspecialchars($order['shipping_pincode']) . '</p>'
    . '<p>We\'ll email you again with updates as your order ships.</p>'
    . '</div>';

send_order_email($pdo, $orderId, 'Order confirmed — #' . $orderId . ' — ' . SITE_NAME, $emailBody);

json_response([
    'ok' => true,
    'orderId' => $orderId,
]);

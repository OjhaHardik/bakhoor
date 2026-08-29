<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$productId = (int)($body['productId'] ?? 0);
$quantity = max(1, (int)($body['quantity'] ?? 1));
$shipping = is_array($body['shipping'] ?? null) ? $body['shipping'] : [];

foreach (['name', 'email', 'phone', 'address', 'city', 'state', 'pincode'] as $field) {
    if (trim((string)($shipping[$field] ?? '')) === '') {
        json_error("Shipping field \"$field\" is required.");
    }
}

if (!filter_var($shipping['email'], FILTER_VALIDATE_EMAIL)) {
    json_error('Enter a valid shipping email address.');
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id, name, price_paise, stock FROM products WHERE id = ? AND is_active = 1');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    json_error('Product not found.', 404);
}

if ($quantity > (int)$product['stock']) {
    json_error('Not enough stock for that quantity.');
}

$user = current_user();
$totalPaise = (int)$product['price_paise'] * $quantity;

$pdo->beginTransaction();

$stmt = $pdo->prepare(
    'INSERT INTO orders
        (user_id, guest_name, guest_email, guest_phone, shipping_address, shipping_city, shipping_state, shipping_pincode, total_paise, status, is_demo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?)'
);
$stmt->execute([
    $user['id'] ?? null,
    $shipping['name'],
    $shipping['email'],
    $shipping['phone'],
    $shipping['address'],
    $shipping['city'],
    $shipping['state'],
    $shipping['pincode'],
    $totalPaise,
    DEMO_MODE ? 1 : 0,
]);
$orderId = (int)$pdo->lastInsertId();

$pdo->prepare(
    'INSERT INTO order_items (order_id, product_id, product_name, unit_price_paise, quantity) VALUES (?, ?, ?, ?, ?)'
)->execute([$orderId, $product['id'], $product['name'], $product['price_paise'], $quantity]);

if (DEMO_MODE) {
    $razorpayOrderId = 'demo_order_' . bin2hex(random_bytes(8));
    $pdo->prepare('UPDATE orders SET razorpay_order_id = ? WHERE id = ?')->execute([$razorpayOrderId, $orderId]);
    $pdo->commit();

    json_response([
        'ok' => true,
        'demoMode' => true,
        'orderId' => $orderId,
        'razorpayOrderId' => $razorpayOrderId,
        'amountPaise' => $totalPaise,
        'productName' => $product['name'],
    ]);
}

// Live mode — create a real order with Razorpay.
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'amount' => $totalPaise,
        'currency' => CURRENCY,
        'receipt' => 'order_' . $orderId,
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$razorpayOrder = json_decode((string)$response, true);

if ($httpCode >= 300 || empty($razorpayOrder['id'])) {
    $pdo->rollBack();
    json_error('Could not start payment. Please try again.', 502);
}

$pdo->prepare('UPDATE orders SET razorpay_order_id = ? WHERE id = ?')->execute([$razorpayOrder['id'], $orderId]);
$pdo->commit();

json_response([
    'ok' => true,
    'demoMode' => false,
    'orderId' => $orderId,
    'razorpayOrderId' => $razorpayOrder['id'],
    'razorpayKeyId' => RAZORPAY_KEY_ID,
    'amountPaise' => $totalPaise,
    'productName' => $product['name'],
]);

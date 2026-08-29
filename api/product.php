<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$stmt = db()->query('SELECT id, name, description, price_paise, image_path, stock FROM products WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
$product = $stmt->fetch();

if (!$product) {
    json_error('No product available right now.', 404);
}

json_response(['ok' => true, 'product' => $product]);

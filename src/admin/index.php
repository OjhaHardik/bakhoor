<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$stats = $pdo->query(
    'SELECT
        COUNT(*) AS order_count,
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_paise ELSE 0 END), 0) AS revenue_paise,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_count
     FROM orders'
)->fetch();

$recentOrders = $pdo->query(
    'SELECT o.id, o.status, o.total_paise, o.created_at, COALESCE(u.name, o.guest_name) AS customer_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC
     LIMIT 5'
)->fetchAll();
?>

<h1 class="admin-h1">Dashboard</h1>

<div class="admin-stats">
  <div class="admin-stat-card">
    <p class="admin-stat-card__label">Total orders</p>
    <p class="admin-stat-card__value"><?= (int)$stats['order_count'] ?></p>
  </div>
  <div class="admin-stat-card">
    <p class="admin-stat-card__label">Revenue (paid)</p>
    <p class="admin-stat-card__value">₹<?= rupees((int)$stats['revenue_paise']) ?></p>
  </div>
  <div class="admin-stat-card">
    <p class="admin-stat-card__label">Pending orders</p>
    <p class="admin-stat-card__value"><?= (int)$stats['pending_count'] ?></p>
  </div>
</div>

<h2 class="admin-h2">Recent orders</h2>
<table class="admin-table">
  <thead>
    <tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr>
  </thead>
  <tbody>
    <?php foreach ($recentOrders as $order): ?>
      <tr>
        <td>#<?= (int)$order['id'] ?></td>
        <td><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
        <td>₹<?= rupees((int)$order['total_paise']) ?></td>
        <td><span class="admin-badge admin-badge--<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
        <td><?= htmlspecialchars(date('d M Y, g:ia', strtotime($order['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recentOrders): ?>
      <tr><td colspan="5">No orders yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

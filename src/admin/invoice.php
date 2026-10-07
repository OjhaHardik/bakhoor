<?php
// Admin — Invoice / Shipping Label
// Print-friendly view for a single order: a customer-facing invoice
// (for records/accounting) and a courier-facing shipping label
// (SHIP TO block to print and stick on the parcel). Two buttons
// below toggle which one goes to the printer via body.printing-*
// classes + @media print rules in admin.css.
$pageTitle = 'Invoice';
require_once __DIR__ . '/includes/header.php';

$pdo = db();

$orderId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT o.*, u.name AS account_name, u.email AS account_email, u.phone AS account_phone
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo '<h1 class="admin-h1">Invoice</h1><p>Order not found.</p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$itemsStmt = $pdo->prepare('SELECT product_name, unit_price_paise, quantity FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$customerName = $order['account_name'] ?? $order['guest_name'];
$customerEmail = $order['account_email'] ?? $order['guest_email'];
$customerPhone = $order['account_phone'] ?? $order['guest_phone'];
?>

<h1 class="admin-h1 no-print">Invoice — <?= htmlspecialchars($order['invoice_number']) ?></h1>

<div class="invoice-actions no-print">
  <button type="button" class="admin-btn" onclick="printDoc('invoice')">Print Invoice</button>
  <button type="button" class="admin-btn admin-btn--ghost" onclick="printDoc('label')">Print Shipping Label</button>
  <a href="orders.php" class="admin-btn admin-btn--ghost">&larr; Back to Orders</a>
</div>

<!-- ============ INVOICE (4in x 6in) ============ -->
<div id="invoice-doc" class="print-doc print-doc--invoice">
  <div class="invoice-top">
    <div>
      <p class="invoice-doc-type"><?= COMPANY_GSTIN !== '' ? 'Tax Invoice' : 'Invoice' ?></p>
      <p class="invoice-doc-sub">Original for Recipient</p>
    </div>
    <p class="invoice-brand">Bakhoor Al Barkaah</p>
  </div>

  <div class="invoice-meta-row">
    <div>
      <p class="invoice-label">Invoice No.</p>
      <p><?= htmlspecialchars($order['invoice_number']) ?></p>
    </div>
    <div>
      <p class="invoice-label">Order Ref.</p>
      <p>#<?= (int)$order['id'] ?></p>
    </div>
    <div>
      <p class="invoice-label">Date</p>
      <p><?= htmlspecialchars(date('d M Y', strtotime($order['created_at']))) ?></p>
    </div>
    <div>
      <p class="invoice-label">Payment</p>
      <p><?= $order['status'] === 'paid' ? 'Paid (Razorpay)' : ucfirst($order['status']) ?></p>
    </div>
  </div>

  <div class="invoice-parties">
    <div>
      <p class="invoice-label">Sold By</p>
      <p class="invoice-party-name">Bakhoor Al Barkaah</p>
      <p><?= htmlspecialchars(COMPANY_ADDRESS_LINE1) ?>, <?= htmlspecialchars(COMPANY_ADDRESS_LINE2) ?></p>
      <p><?= htmlspecialchars(COMPANY_PHONE) ?> &middot; <?= htmlspecialchars(COMPANY_EMAIL) ?></p>
      <?php if (COMPANY_GSTIN !== ''): ?>
        <p>GSTIN: <?= htmlspecialchars(COMPANY_GSTIN) ?></p>
      <?php endif; ?>
    </div>
    <div>
      <p class="invoice-label">Bill To / Ship To</p>
      <p class="invoice-party-name"><?= htmlspecialchars($customerName) ?></p>
      <p><?= htmlspecialchars($order['shipping_address']) ?></p>
      <p><?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> — <?= htmlspecialchars($order['shipping_pincode']) ?></p>
      <p><?= htmlspecialchars($customerPhone) ?></p>
      <p><?= htmlspecialchars($customerEmail) ?></p>
    </div>
  </div>

  <table class="invoice-table">
    <thead>
      <tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['product_name']) ?></td>
          <td><?= (int)$item['quantity'] ?></td>
          <td>&#8377;<?= rupees((int)$item['unit_price_paise']) ?></td>
          <td>&#8377;<?= rupees((int)$item['unit_price_paise'] * (int)$item['quantity']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="invoice-totals">
    <div class="invoice-totals-row">
      <span>Subtotal</span>
      <span>&#8377;<?= rupees((int)$order['subtotal_paise']) ?></span>
    </div>
    <?php if ((int)$order['discount_paise'] > 0): ?>
      <div class="invoice-totals-row">
        <span>Discount</span>
        <span>&minus;&#8377;<?= rupees((int)$order['discount_paise']) ?></span>
      </div>
    <?php endif; ?>
    <div class="invoice-totals-row invoice-totals-row--final">
      <span>Total</span>
      <span>&#8377;<?= rupees((int)$order['total_paise']) ?></span>
    </div>
  </div>

  <p class="invoice-words"><strong>Amount in Words:</strong> <?= htmlspecialchars(amount_in_words((int)$order['total_paise'])) ?></p>

  <p class="invoice-footer">Goods once sold are governed by our Refund &amp; Return Policy. This is a system-generated invoice and does not require a signature.</p>
</div>

<!-- ============ SHIPPING LABEL ============ -->
<div id="label-doc" class="print-doc print-doc--label">
  <p class="label-from">Ship from: Bakhoor Al Barkaah</p>
  <div class="label-ship-to">
    <p class="label-heading">SHIP TO</p>
    <p class="label-name"><?= htmlspecialchars($customerName) ?></p>
    <p><?= htmlspecialchars($order['shipping_address']) ?></p>
    <p><?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> &mdash; <?= htmlspecialchars($order['shipping_pincode']) ?></p>
    <p><?= htmlspecialchars($customerPhone) ?></p>
  </div>
  <div class="label-meta">
    <p><strong>Invoice:</strong> <?= htmlspecialchars($order['invoice_number']) ?></p>
    <p><strong>Payment:</strong> <?= $order['status'] === 'paid' ? 'PREPAID' : strtoupper($order['status']) ?></p>
    <p><strong>Amount:</strong> &#8377;<?= rupees((int)$order['total_paise']) ?></p>
  </div>
</div>

<script>
function printDoc(which) {
  document.body.classList.remove('printing-invoice', 'printing-label');
  document.body.classList.add('printing-' + which);
  window.print();
}
window.addEventListener('afterprint', function () {
  document.body.classList.remove('printing-invoice', 'printing-label');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

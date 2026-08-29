<?php
// Admin — Orders
// Lists every order with its items/total/status, and a per-order
// panel (see .admin-mail-toggle) for emailing the customer an
// update. Two POST actions land on this same page: update_status
// (the inline status <select>) and send_email (the compose form).
$pageTitle = 'Orders';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/mailer.php';

$pdo = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    // ---- Action: change an order's status ----
    if (($_POST['action'] ?? '') === 'update_status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['pending', 'paid', 'failed', 'cancelled'], true)) {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, (int)$_POST['id']]);
        }
        header('Location: orders.php');
        exit;
    }

    // ---- Action: send a manual order-update email ----
    if (($_POST['action'] ?? '') === 'send_email') {
        $orderId = (int)$_POST['id'];
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($subject === '' || $message === '') {
            $flash = ['type' => 'error', 'text' => 'Subject and message are both required.'];
        } else {
            $bodyHtml = '<div style="font-family:Arial,sans-serif; color:#1e1e1e; max-width:480px; white-space:pre-line;">'
                . htmlspecialchars($message) . '</div>';
            $result = send_order_email($pdo, $orderId, $subject, $bodyHtml);
            $flash = $result['ok']
                ? ['type' => 'success', 'text' => $result['status'] === 'demo'
                    ? "Email logged in demo mode (SMTP isn't configured yet, so nothing was actually sent)."
                    : 'Email sent to the customer.']
                : ['type' => 'error', 'text' => 'Could not send email: ' . $result['error']];
        }
    }
}

// One order per row, plus its line items and email log fetched
// per-row below (small N, simplicity over a single mega-join).
$orders = $pdo->query(
    'SELECT o.*, u.name AS account_name, u.email AS account_email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC'
)->fetchAll();

$itemsStmt = $pdo->prepare('SELECT product_name, unit_price_paise, quantity FROM order_items WHERE order_id = ?');
$emailsStmt = $pdo->prepare('SELECT subject, status, created_at FROM order_emails WHERE order_id = ? ORDER BY created_at DESC');
?>

<h1 class="admin-h1">Orders</h1>

<!-- Flash message from the send_email / update_status actions above -->
<?php if ($flash): ?>
  <p class="admin-alert admin-alert--<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<table class="admin-table admin-table--orders">
  <thead>
    <tr><th>ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th>Emails</th></tr>
  </thead>
  <tbody>
    <?php foreach ($orders as $order): ?>
      <?php
      $itemsStmt->execute([$order['id']]);
      $items = $itemsStmt->fetchAll();
      $emailsStmt->execute([$order['id']]);
      $emails = $emailsStmt->fetchAll();
      $customerName = $order['account_name'] ?? $order['guest_name'];
      $customerEmail = $order['account_email'] ?? $order['guest_email'];
      ?>
      <tr>
        <td>#<?= (int)$order['id'] ?><?= $order['is_demo'] ? ' <span class="admin-badge">demo</span>' : '' ?></td>
        <td>
          <?= htmlspecialchars($customerName) ?><br>
          <span class="admin-muted"><?= htmlspecialchars($customerEmail) ?></span>
          <?= $order['account_name'] ? '' : '<br><span class="admin-muted">guest checkout</span>' ?>
        </td>
        <td>
          <?php foreach ($items as $item): ?>
            <div><?= (int)$item['quantity'] ?> × <?= htmlspecialchars($item['product_name']) ?></div>
          <?php endforeach; ?>
        </td>
        <td>₹<?= rupees((int)$order['total_paise']) ?></td>
        <td>
          <form method="post" class="admin-inline-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <select name="status" onchange="this.form.submit()" class="admin-badge admin-badge--<?= htmlspecialchars($order['status']) ?>">
              <?php foreach (['pending', 'paid', 'failed', 'cancelled'] as $status): ?>
                <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td><?= htmlspecialchars(date('d M Y, g:ia', strtotime($order['created_at']))) ?></td>

        <!-- Per-order email panel: collapsed <details> showing a
             send-count/"Send update" summary, expanding into the
             prior send log plus a compose form (posts send_email). -->
        <td>
          <details class="admin-mail-toggle">
            <summary><?= count($emails) ? count($emails) . ' sent' : 'Send update' ?></summary>

            <?php if ($emails): ?>
              <ul class="admin-mail-log">
                <?php foreach ($emails as $log): ?>
                  <li>
                    <span class="admin-badge admin-badge--<?= $log['status'] === 'sent' ? 'paid' : ($log['status'] === 'demo' ? 'pending' : 'failed') ?>"><?= htmlspecialchars($log['status']) ?></span>
                    <?= htmlspecialchars($log['subject']) ?>
                    <span class="admin-muted"><?= htmlspecialchars(date('d M, g:ia', strtotime($log['created_at']))) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <form method="post" class="admin-mail-form">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_email">
              <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
              <input type="text" name="subject" placeholder="Subject" value="Update on your order #<?= (int)$order['id'] ?>" required>
              <textarea name="message" rows="3" placeholder="Message to the customer…" required></textarea>
              <button type="submit" class="admin-btn admin-btn-full">Send email</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
      <tr><td colspan="7">No orders yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

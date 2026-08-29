<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$error = null;
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int)$_POST['id']]);
        header('Location: products.php');
        exit;
    }

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priceRupees = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $imagePath = trim($_POST['image_path'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $priceRupees <= 0) {
            $error = 'Name and a price greater than 0 are required.';
        } else {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            $pricePaise = (int)round($priceRupees * 100);

            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE products SET name=?, slug=?, description=?, price_paise=?, image_path=?, stock=?, is_active=? WHERE id=?'
                )->execute([$name, $slug, $description, $pricePaise, $imagePath, $stock, $isActive, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO products (name, slug, description, price_paise, image_path, stock, is_active) VALUES (?,?,?,?,?,?,?)'
                )->execute([$name, $slug, $description, $pricePaise, $imagePath, $stock, $isActive]);
            }

            header('Location: products.php');
            exit;
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
?>

<h1 class="admin-h1">Products</h1>

<?php if ($error): ?>
  <p class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<section class="admin-panel">
  <h2 class="admin-h2"><?= $editing ? 'Edit product' : 'Add a product' ?></h2>
  <form method="post" class="admin-form admin-form--grid">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <label class="admin-field">
      <span>Name</span>
      <input type="text" name="name" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
    </label>

    <label class="admin-field">
      <span>Price (₹)</span>
      <input type="number" name="price" step="0.01" min="0.01" required
             value="<?= $editing ? htmlspecialchars((string)($editing['price_paise'] / 100)) : '' ?>">
    </label>

    <label class="admin-field">
      <span>Stock</span>
      <input type="number" name="stock" min="0" required value="<?= htmlspecialchars((string)($editing['stock'] ?? 0)) ?>">
    </label>

    <label class="admin-field">
      <span>Image path</span>
      <input type="text" name="image_path" placeholder="assets/images/about/about-box.png"
             value="<?= htmlspecialchars($editing['image_path'] ?? '') ?>">
    </label>

    <label class="admin-field admin-field--full">
      <span>Description</span>
      <textarea name="description" rows="3"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
    </label>

    <label class="admin-check">
      <input type="checkbox" name="is_active" <?= (!$editing || $editing['is_active']) ? 'checked' : '' ?>>
      <span>Active (visible on site)</span>
    </label>

    <div class="admin-field--full">
      <button type="submit" class="admin-btn"><?= $editing ? 'Save changes' : 'Add product' ?></button>
      <?php if ($editing): ?>
        <a href="products.php" class="admin-btn admin-btn--ghost">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<h2 class="admin-h2">All products</h2>
<table class="admin-table">
  <thead>
    <tr><th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($products as $product): ?>
      <tr>
        <td><?= htmlspecialchars($product['name']) ?></td>
        <td>₹<?= rupees((int)$product['price_paise']) ?></td>
        <td><?= (int)$product['stock'] ?></td>
        <td><span class="admin-badge admin-badge--<?= $product['is_active'] ? 'paid' : 'cancelled' ?>"><?= $product['is_active'] ? 'active' : 'hidden' ?></span></td>
        <td class="admin-table__actions">
          <a href="products.php?edit=<?= (int)$product['id'] ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete this product?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
            <button type="submit" class="admin-link-btn">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

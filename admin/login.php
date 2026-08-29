<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$error = null;

if (($u = current_user()) && $u['is_admin']) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $stmt = db()->prepare('SELECT id, password_hash, is_admin FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            $error = 'Incorrect email or password.';
        } elseif (!$user['is_admin']) {
            $error = 'This account does not have admin access.';
        } else {
            log_in_user((int)$user['id']);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Bakhoor Al Barkaah</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Infant:wght@500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-auth-body">
  <main class="admin-auth-card">
    <h1 class="admin-auth-title">Admin sign in</h1>
    <p class="admin-auth-sub">Bakhoor Al Barkaah dashboard</p>

    <?php if ($error): ?>
      <p class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" class="admin-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label class="admin-field">
        <span>Email</span>
        <input type="email" name="email" required autofocus>
      </label>
      <label class="admin-field">
        <span>Password</span>
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="admin-btn admin-btn-full">Sign in</button>
    </form>
  </main>
</body>
</html>

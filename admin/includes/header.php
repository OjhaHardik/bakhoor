<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$admin = require_admin();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Admin — Bakhoor Al Barkaah</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Infant:wght@500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar">
    <p class="admin-sidebar__brand">Bakhoor Al Barkaah</p>
    <nav class="admin-sidebar__nav">
      <a href="index.php" class="<?= $currentPage === 'index.php' ? 'is-active' : '' ?>">Dashboard</a>
      <a href="products.php" class="<?= $currentPage === 'products.php' ? 'is-active' : '' ?>">Products</a>
      <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'is-active' : '' ?>">Orders</a>
    </nav>
    <form method="post" action="logout.php" class="admin-sidebar__logout">
      <button type="submit">Sign out (<?= htmlspecialchars($admin['name']) ?>)</button>
    </form>
  </aside>
  <main class="admin-main">

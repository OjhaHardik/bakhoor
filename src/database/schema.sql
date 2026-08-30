-- Bakhoor Al Barkaah — database schema
-- Run once to set up local (XAMPP) or production (Hostinger) MySQL.

CREATE DATABASE IF NOT EXISTS bakhoor_al_barkaah
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bakhoor_al_barkaah;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  google_id VARCHAR(64) NULL UNIQUE,
  phone VARCHAR(20) NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  description TEXT NULL,
  price_paise INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NULL,
  stock INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  guest_name VARCHAR(120) NULL,
  guest_email VARCHAR(190) NULL,
  guest_phone VARCHAR(20) NULL,
  shipping_address VARCHAR(255) NOT NULL,
  shipping_city VARCHAR(100) NOT NULL,
  shipping_state VARCHAR(100) NOT NULL,
  shipping_pincode VARCHAR(12) NOT NULL,
  total_paise INT UNSIGNED NOT NULL,
  status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  razorpay_order_id VARCHAR(64) NULL,
  razorpay_payment_id VARCHAR(64) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(160) NOT NULL,
  unit_price_paise INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_emails (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  sent_to VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('sent','demo','failed') NOT NULL DEFAULT 'sent',
  error VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_emails_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed product (edit via /admin/products.php)
INSERT INTO products (name, slug, description, price_paise, image_path, stock)
SELECT 'Bakhoor Al Barkaah', 'bakhoor-al-barkaah',
  'A fragrant blend of woods, resins, and oils, burned to create a rich, warm, and luxurious aroma.',
  49900, 'assets-optimized/images/about/about-2.webp', 100
WHERE NOT EXISTS (SELECT 1 FROM products WHERE slug = 'bakhoor-al-barkaah');

-- Seed admin user — email: admin@bakhooralbarkaah.com / password: admin123
-- CHANGE THIS PASSWORD after first login (admin/products.php has no self-service change yet — update via SQL or phpMyAdmin).
INSERT INTO users (name, email, password_hash, is_admin)
SELECT 'Admin', 'admin@bakhooralbarkaah.com', '$2y$10$fCAu61yqK13UiSPnzSDrs.QvECvGZdgG7Mc7eGv5IQMXHQvGT9Seq', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@bakhooralbarkaah.com');

<?php
// Copy this file to config.php and fill in your own local/production values.
// config.php is gitignored so real credentials never get committed.

// Database credentials — for local XAMPP dev. Update for Hostinger before deploying.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'bakhoor_al_barkaah');
define('DB_USER', 'root');
define('DB_PASS', '');

// Razorpay — leave blank to run checkout in demo mode (simulated payment, no real charge).
// Fill both in to go live: https://dashboard.razorpay.com/app/keys
define('RAZORPAY_KEY_ID', '');
define('RAZORPAY_KEY_SECRET', '');
define('DEMO_MODE', RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '');

// Google Sign-In — leave blank to hide the "Continue with Google" button.
// Create a Client ID at https://console.cloud.google.com/apis/credentials
define('GOOGLE_CLIENT_ID', '');

// Outgoing email (SMTP) — leave SMTP_HOST blank to run in demo mode
// (emails are logged to the order_emails table but not actually sent).
// Most hosts (incl. Hostinger) give you these under Emails → Email Accounts.
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'no-reply@bakhooralbarkaah.com');
define('SMTP_FROM_NAME', 'Bakhoor Al Barkaah');
define('MAIL_DEMO_MODE', SMTP_HOST === '');

define('SITE_NAME', 'Bakhoor Al Barkaah');
define('CURRENCY', 'INR');

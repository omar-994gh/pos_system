<?php
// public/header.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();

// جلب آخر إعدادات النظام
$stmt = $db->query("SELECT restaurant_name, logo_path FROM System_Settings ORDER BY id DESC LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// قيم افتراضية
$restaurantName = $settings['restaurant_name'] ?? 'اسم المطعم';
$logoPath       = 'images/logo.png';

$username = $_SESSION['username'];
$role     = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($restaurantName) ?></title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="../assets/bootstrap.min.css">
  <!-- Global styles -->
  <link rel="stylesheet" href="css/style.css">
  <!-- jQuery (لـ Bootstrap 4) -->
  <script src="../assets/jquery.min.js"></script>
  <!-- Popper.js -->
  <script src="../assets/popper.min.js"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</head>
<body>
  <header class="p-3 bg-light d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="شعار" height="50">
      <span class="h5 mx-3"><?= htmlspecialchars($restaurantName) ?></span>
    </div>
    <nav class="no-print">
      <a href="dashboard.php" class="btn btn-sm btn-outline-primary mx-1">الرئيسية</a>
      <a href="pos.php" class="btn btn-sm btn-outline-warning mx-1">المبيع</a>
      <?php if (Auth::isAdmin()): ?>
        <a href="settings.php" class="btn btn-sm btn-outline-secondary mx-1">الإعدادات</a>
      <?php endif; ?>
      <?php if (Auth::isAdmin()): ?>
        <a href="stock_report.php" class="btn btn-sm btn-outline-danger mx-1">جرد المخزن</a>
      <?php endif; ?>
      <?php if (Auth::isAdmin()): ?>
        <a href="users.php" class="btn btn-sm btn-outline-success mx-1">المستخدمين</a>
      <?php endif; ?>
      <?php if (Auth::isAdmin()): ?>
        <a href="warehouse_entries.php" class="btn btn-sm btn-outline-info mx-1">فواتير إدخال/إخراج</a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-sm btn-danger mx-1">خروج (<?= htmlspecialchars($username) ?>)</a>
    </nav>
  </header>
  <main class="container mt-4">

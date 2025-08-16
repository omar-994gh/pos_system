<?php
// public/settings.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

// جلب آخر إعدادات
$stmt = $db->prepare("SELECT * FROM System_Settings ORDER BY id DESC LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// إذا لم توجد إعدادات، ابتداءً بقيم فارغة
$restaurantName   = $settings['restaurant_name']   ?? '';
$logoPath         = $settings['logo_path']         ?? '';
$taxNumber        = $settings['tax_number']        ?? '';
$address          = $settings['address']           ?? '';
$taxRate          = $settings['tax_rate']          ?? 0;
$printWidthMm     = $settings['print_width_mm']    ?? 80;
$currency         = $settings['currency']          ?? 'SYP';

// حقول الطباعة
$printFields = [
  'field_item_name_ar',
  'field_item_name_en',
  'field_tax_number',
  'field_username',
  'field_restaurant_name',
  'field_restaurant_logo',
];
$fieldValues = [];
foreach ($printFields as $f) {
    // نتأكد من وجود العمود
    $fieldValues[$f] = (!empty($settings[$f]) && $settings[$f] == 1) ? 1 : 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إعدادات النظام</title>
  <link rel="stylesheet" href="../assets/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <?php include 'header.php'; ?>
  <main class="container mt-4">
    <h2>إعدادات النظام</h2>
    <a href="db_backup.php" class="btn btn-sm btn-outline-danger mx-1">النسخ الاحتياطي</a>
    <form action="settings_handler.php" method="post" enctype="multipart/form-data">
      <!-- اسم المطعم -->
      <div class="mb-3">
        <label>اسم المنظّمة/المطعم</label>
        <input type="text" name="restaurant_name" class="form-control"
               value="<?= htmlspecialchars($restaurantName) ?>" required>
      </div>

      <!-- شعار -->
      <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($logoPath) ?>">
      <div class="mb-3">
        <label>الشعار (تحميل صورة)</label>
        <input type="file" name="logo_file" class="form-control">
        <?php if ($logoPath): ?>
          <!-- تأكد أن المسار صحيح: public/images/logo.png -->
          <img src="<?= htmlspecialchars($logoPath) ?>" alt="الشعار" height="80" class="mt-2">
        <?php endif; ?>
      </div>

      <!-- الرقم الضريبي -->
      <div class="mb-3">
        <label>الرقم الضريبي</label>
        <input type="text" name="tax_number" class="form-control"
               value="<?= htmlspecialchars($taxNumber) ?>">
      </div>

      <!-- العنوان -->
      <div class="mb-3">
        <label>العنوان</label>
        <input type="text" name="address" class="form-control"
               value="<?= htmlspecialchars($address) ?>">
      </div>

      <!-- نسبة الضريبة -->
      <div class="mb-3">
        <label>نسبة الضريبة (%)</label>
        <input type="number" step="0.01" name="tax_rate" class="form-control"
               value="<?= htmlspecialchars($taxRate) ?>">
      </div>

      <!-- عرض الفاتورة بالمليمتر -->
      <div class="mb-3">
        <label>عرض الفاتورة (مم)</label>
        <input type="number" name="print_width_mm" class="form-control"
               value="<?= htmlspecialchars($printWidthMm) ?>">
      </div>

      <!-- اختيار العملة -->
      <div class="mb-3">
        <label>العملة الافتراضية</label>
        <select name="currency" class="form-control">
          <option value="SYP" <?= $currency==='SYP'?'selected':'' ?>>ليرة سورية (SYP)</option>
          <option value="USD" <?= $currency==='USD'?'selected':'' ?>>دولار أمريكي (USD)</option>
          <option value="TRY" <?= $currency==='TRY'?'selected':'' ?>>ليرة تركية (TRY)</option>
        </select>
      </div>

      <!-- خيارات الحقول للطباعة -->
      <h4 class="mt-4">اختر الحقول للطباعة</h4>
      <?php
        $labels = [
          'field_item_name_ar'    => 'اسم المادة بالعربي',
          'field_item_name_en'    => 'اسم المادة بالإنكليزي',
          'field_tax_number'      => 'الرقم الضريبي',
          'field_username'        => 'اسم المستخدم',
          'field_restaurant_name' => 'اسم المطعم/المنشأة',
          'field_restaurant_logo' => 'شعار المنشأة',
        ];
        foreach ($labels as $key => $label):
      ?>
      <div class="form-check">
        <input class="form-check-input"
               type="checkbox"
               name="<?= $key ?>"
               id="<?= $key ?>"
               value="1"
               <?= $fieldValues[$key] ? 'checked' : '' ?>>
        <label class="form-check-label mr-3" for="<?= $key ?>"><?= $label ?></label>
      </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-success mt-3">حفظ الإعدادات</button>
    </form>
  </main>
</body>
</html>

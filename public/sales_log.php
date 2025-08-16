<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/SalesLog.php';

Auth::requireLogin();
if (!Auth::isAdmin()) { header('Location: dashboard.php'); exit; }

$from = $_GET['date_from'] ?? null;
$to   = $_GET['date_to']   ?? null;

$model = new SalesLog($db);
$summary = $model->summary($from, $to);
$details = $model->details($from, $to);
?>
<?php include 'header.php'; ?>

<main class="container mt-4">
  <h2>سجل المبيعات</h2>
  <div class="d-flex justify-content-between">
    <form class="row g-2 mb-4">
      <div class="col-auto">
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($from) ?>" placeholder="من">
      </div>
      <div class="col-auto">
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($to) ?>" placeholder="إلى">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary">تصفية</button>
      </div>
    </form>

    <a href="orders_report.php" class="btn btn-outline-success mb-3">فاتورة المبيعات</a>

  </div>

  <h4>الإحصاءات حسب المستخدم</h4>
  <table class="table table-bordered mb-5">
    <thead>
      <tr>
        <th>المستخدم</th>
        <th>عدد الفواتير</th>
        <th>إجمالي المبيعات</th>
        <th>متوسط قيمة الفاتورة</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($summary as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= $row['sale_count'] ?></td>
        <td><?= number_format($row['total_amount'], 2) ?></td>
        <td><?= number_format($row['avg_amount'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($summary)): ?>
      <tr><td colspan="4" class="text-center">لا توجد بيانات</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h4>تفاصيل الفواتير</h4>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>#</th>
        <th>التاريخ والوقت</th>
        <th>المستخدم</th>
        <th>الإجمالي</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($details as $o): ?>
      <tr>
        <td><?= $o['order_id'] ?></td>
        <td><?= htmlspecialchars($o['created_at']) ?></td>
        <td><?= htmlspecialchars($o['username']) ?></td>
        <td><?= number_format($o['total'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($details)): ?>
      <tr><td colspan="4" class="text-center">لا توجد فواتير</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>

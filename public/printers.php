<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Printer.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$printerModel = new Printer($db);
$printers     = $printerModel->all();
?>
<?php include 'header.php'; ?>

<h2>إدارة الطابعات</h2>
<a href="printer_form.php" class="btn btn-success mb-3">+ إضافة طابعة جديدة</a>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>#</th>
      <th>الاسم</th>
      <th>العنوان (IP/USB)</th>
      <th>تاريخ الإنشاء</th>
      <th>إجراءات</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($printers as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['id']) ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td><?= htmlspecialchars($p['address']) ?></td>
        <td><?= htmlspecialchars($p['created_at']) ?></td>
        <td>
          <a href="printer_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">تعديل</a>
          <a href="printer_delete.php?id=<?= $p['id'] ?>"
             onclick="return confirm('هل تريد حذف هذه الطابعة؟');"
             class="btn btn-sm btn-danger">حذف</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</main>
<script src="../assets/bootstrap.min.js"></script>
</body>
</html>

<?php
// public/users.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/User.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$userModel = new User($db);
$users = $userModel->all();
?>
<?php include 'header.php'; ?>

<h2>إدارة المستخدمين</h2>
<a href="user_form.php" class="btn btn-success mb-3">+ إضافة مستخدم جديد</a>

<table class="table table-bordered table-striped">
  <thead>
    <tr>
      <th>#</th>
      <th>اسم المستخدم</th>
      <th>الدور</th>
      <th>تاريخ الإنشاء</th>
      <th>إجراءات</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['id']) ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td><?= htmlspecialchars($u['created_at']) ?></td>
        <td>
          <a href="user_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-primary">تعديل</a>
          <a href="user_delete.php?id=<?= $u['id'] ?>" 
             onclick="return confirm('هل أنت متأكد من حذف المستخدم؟');"
             class="btn btn-sm btn-danger">
            حذف
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</main>
<script src="../assets/bootstrap.min.js"></script>
</body>
</html>

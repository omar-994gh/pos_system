<?php
// public/items.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Group.php';
require_once __DIR__ . '/../src/Item.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$groupModel = new Group($db);
$itemModel  = new Item($db);

$groups = $groupModel->all();
$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
$items = $selectedGroupId ? $itemModel->allByGroup($selectedGroupId) : [];
?>
<?php include 'header.php'; ?>

<div class="row">
  <div class="col-md-3">
    <h4>المجموعات</h4>
    <ul class="list-group">
      <?php foreach ($groups as $g): ?>
        <a href="?group_id=<?= $g['id'] ?>"
           class="list-group-item list-group-item-action
             <?= $selectedGroupId === $g['id'] ? 'active' : '' ?>">
          <?= htmlspecialchars($g['name']) ?>
        </a>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="col-md-9">
    <?php if ($selectedGroupId): ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <?php
        // قبل عرض العنوان، أعد فهرسة الأسماء حسب الـ ID
        $namesById = array_column($groups, 'name', 'id');
        ?>
        <h4>الأصناف في: <?= htmlspecialchars($namesById[$selectedGroupId] ?? '') ?></h4>
        <a href="item_form.php?group_id=<?= $selectedGroupId ?>"
           class="btn btn-success">+ إضافة صنف</a>
      </div>
      <table class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>الاسم (عربي)</th>
            <th>الاسم (إنجليزي)</th>
            <th>باركود</th>
            <th>السعر</th>
            <th>الكمية</th>
            <th>الوحدة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $i): ?>
            <tr>
              <td><?= $i['id'] ?></td>
              <td><?= htmlspecialchars($i['name_ar']) ?></td>
              <td><?= htmlspecialchars($i['name_en']) ?></td>
              <td><?= htmlspecialchars($i['barcode']) ?></td>
              <td><?= htmlspecialchars($i['price']) ?></td>
              <td><?= htmlspecialchars($i['stock']) ?></td>
              <td><?= htmlspecialchars($i['unit']) ?></td>
              <td>
                <a href="item_form.php?id=<?= $i['id'] ?>&group_id=<?= $selectedGroupId ?>" class="btn btn-sm btn-primary">تعديل</a>
                <form action="item_delete.php?id=<?= $i['id'] ?>&group_id=<?= $selectedGroupId ?>" method="post" style="display:inline" onsubmit="return confirm('هل تريد حذف هذا الصنف؟');">
                  <button type="submit" class="btn btn-sm btn-danger" data-auth="btn_delete_item">حذف</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>اختر مجموعة لعرض الأصناف.</p>
    <?php endif; ?>
  </div>
</div>

</main>
<script src="../assets/bootstrap.min.js"></script>
</body>
</html>

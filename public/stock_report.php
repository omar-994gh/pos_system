<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Item.php';

Auth::requireLogin();

$itemModel = new Item($db);
$items = $itemModel->allWithStockAndGroup(); // نحتاج دالة تشمل اسم المجموعة
?>
<?php include 'header.php'; ?>

<main class="container py-4">
  <h2 class="mb-4 text-center">تقرير المخزون</h2>

  <div class="row g-3 mb-3 align-items-end">
    <div class="col-md-4">
      <label for="filterItem" class="form-label">فلترة حسب المادة:</label>
      <select id="filterItem" class="form-select">
        <option value="all">– كل المواد –</option>
        <?php foreach ($items as $item): ?>
          <option value="<?= $item['name_ar'] ?>">
            <?= htmlspecialchars($item['name_ar']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="searchInput" class="form-label">بحث مباشر:</label>
      <input type="text" id="searchInput" class="form-control" placeholder="أدخل اسم المادة...">
    </div>
    <div class="col-md-4 text-end">
      <button class="btn btn-primary mt-3" onclick="printReport()">
        🖨️ طباعة التقرير
      </button>
    </div>
  </div>

  <div class="table-responsive" id="reportArea">
    <table class="table table-bordered table-hover text-center align-middle" id="stockTable">
      <thead class="table-dark">
        <tr>
          <th>اسم المادة</th>
          <th>الاسم الإنجليزي</th>
          <th>المجموعة</th>
          <th>الكمية المتوفرة</th>
          <th>الوحدة</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr data-name="<?= strtolower($it['name_ar']) ?>">
            <td><?= htmlspecialchars($it['name_ar']) ?></td>
            <td><?= htmlspecialchars($it['name_en']) ?></td>
            <td><?= htmlspecialchars($it['group_name']) ?></td>
            <td><?= $it['stock'] ?></td>
            <td><?= htmlspecialchars($it['unit']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const filterSelect = document.getElementById('filterItem');
  const searchInput = document.getElementById('searchInput');
  const rows = document.querySelectorAll('#stockTable tbody tr');

  const filterRows = () => {
    const selected = filterSelect.value.toLowerCase();
    const keyword = searchInput.value.toLowerCase();

    rows.forEach(row => {
      const name = row.dataset.name;
      const matchesFilter = (selected === 'all' || name.includes(selected));
      const matchesSearch = name.includes(keyword);
      row.style.display = (matchesFilter && matchesSearch) ? '' : 'none';
    });
  };

  filterSelect.addEventListener('change', filterRows);
  searchInput.addEventListener('input', filterRows);
});

function printReport() {
  const content = document.getElementById('reportArea').innerHTML;
  const printWindow = window.open('', '', 'width=900,height=700');
  printWindow.document.write(`
    <html>
      <head>
        <title>تقرير المخزون</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
        <style>
          body { direction: rtl; text-align: center; padding: 20px; }
          table { width: 100%; border-collapse: collapse; margin-top: 20px; }
          th, td { border: 1px solid #000; padding: 10px; }
          th { background-color: #eee; }
        </style>
      </head>
      <body>
        <h2>تقرير المواد في المخزون</h2>
        ${content}
        <script>window.onload = () => window.print();<\/script>
      </body>
    </html>
  `);
  printWindow.document.close();
}
</script>

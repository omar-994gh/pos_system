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
  <div class="d-flex justify-content-between align-items-center">
    <form class="row g-2 mb-4">
      <div class="col-auto"><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($from) ?>" placeholder="من"></div>
      <div class="col-auto"><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($to) ?>" placeholder="إلى"></div>
      <div class="col-auto"><button class="btn btn-primary">تصفية</button></div>
    </form>
    <div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="f_username" checked>
        <label class="form-check-label" for="f_username">اسم المستخدم</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="f_total" checked>
        <label class="form-check-label" for="f_total">القيم الإجمالية</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="f_details">
        <label class="form-check-label" for="f_details">تفاصيل الفواتير</label>
      </div>
      <button id="printSales" class="btn btn-outline-primary mt-2">طباعة كإيصال</button>
    </div>
  </div>

  <h4>الإحصاءات حسب المستخدم</h4>
  <table class="table table-bordered mb-5">
    <thead><tr><th>المستخدم</th><th>عدد الفواتير</th><th>إجمالي المبيعات</th><th>متوسط قيمة الفاتورة</th></tr></thead>
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
    <thead><tr><th>#</th><th>التاريخ والوقت</th><th>المستخدم</th><th>الإجمالي</th></tr></thead>
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
<script>
  document.getElementById('printSales').addEventListener('click', async () => {
    const wantUser   = document.getElementById('f_username').checked;
    const wantTotals = document.getElementById('f_total').checked;
    const wantDetails= document.getElementById('f_details').checked;

    const payload = { type:'sales_log', from: '<?= htmlspecialchars($from) ?>', to: '<?= htmlspecialchars($to) ?>', wantUser, wantTotals, wantDetails };
    const resp = await fetch('../src/print.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(await buildSalesImage(payload)) });
    const data = await resp.json();
    if (data.success) { if (typeof showToast==='function') showToast('تم إرسال السجل للطباعة'); }
  });

  async function buildSalesImage(opts) {
    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
    const width = 560; let y = 20; canvas.width = width; canvas.height = 1200;
    ctx.fillStyle = 'white'; ctx.fillRect(0,0,width,canvas.height); ctx.fillStyle = '#000'; ctx.textAlign='center'; ctx.font = 'bold 20px Arial';
    ctx.fillText('سجل المبيعات', width/2, y); y += 30;
    if (opts.from || opts.to) { ctx.font='14px Arial'; ctx.fillText(`من ${opts.from||'-'} إلى ${opts.to||'-'}`, width/2, y); y+=24; }

    <?php $sumTotal = 0; foreach ($details as $o) { $sumTotal += (float)$o['total']; } ?>
    if (opts.wantTotals) {
      ctx.textAlign='left'; ctx.font='16px Arial'; ctx.fillText('الإجمالي الكلي: <?= number_format($sumTotal,2) ?>', 20, y); y+=24;
    }
    if (opts.wantUser) {
      ctx.textAlign='left'; ctx.font='bold 16px Arial'; ctx.fillText('حسب المستخدم:', 20, y); y+=22;
      ctx.font='14px Arial';
      <?php foreach ($summary as $row): ?>
        ctx.fillText('<?= addslashes($row['username']) ?> - عدد: <?= (int)$row['sale_count'] ?> | إجمالي: <?= number_format($row['total_amount'],2) ?>', 20, y); y+=20;
      <?php endforeach; ?>
      y+=8;
    }
    if (opts.wantDetails) {
      ctx.textAlign='left'; ctx.font='bold 16px Arial'; ctx.fillText('تفاصيل:', 20, y); y+=22; ctx.font='14px Arial';
      <?php foreach ($details as $o): ?>
        ctx.fillText('#<?= (int)$o['order_id'] ?> | <?= addslashes($o['created_at']) ?> | <?= addslashes($o['username']) ?> | <?= number_format($o['total'],2) ?>', 20, y); y+=18;
      <?php endforeach; ?>
    }
    return { images:[{ image: canvas.toDataURL('image/png') }] };
  }
</script>

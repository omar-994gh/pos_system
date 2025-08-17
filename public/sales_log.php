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
      <a href="summary_groups.php" class="btn btn-outline-success mb-2">ملخص المجموعات</a>
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
    const width = 560; let y = 16; canvas.width = width; canvas.height = 1600;
    ctx.fillStyle = '#fff'; ctx.fillRect(0,0,width,canvas.height);

    // Header
    ctx.fillStyle = '#111'; ctx.textAlign='center'; ctx.font = 'bold 22px Arial';
    ctx.fillText('سجل المبيعات', width/2, y); y+=28;
    ctx.font = '14px Arial';
    if (opts.from || opts.to) { ctx.fillText(`من ${opts.from||'-'} إلى ${opts.to||'-'}`, width/2, y); y+=22; }
    // Separator
    ctx.strokeStyle='#000'; ctx.beginPath(); ctx.moveTo(12,y); ctx.lineTo(width-12,y); ctx.stroke(); y+=10;

    // Totals row
    <?php $sumTotal = 0; foreach ($details as $o) { $sumTotal += (float)$o['total']; } ?>
    if (opts.wantTotals) {
      ctx.textAlign='left'; ctx.font='bold 16px Arial'; ctx.fillText('الإجمالي الكلي', 14, y);
      ctx.textAlign='right'; ctx.fillText('<?= number_format($sumTotal,2) ?>', width-14, y); y+=24;
    }

    // Summary table
    if (opts.wantUser) {
      const col1=14, col2=width-14; const rowH=22;
      ctx.textAlign='left'; ctx.font='bold 16px Arial'; ctx.fillText('حسب المستخدم', 14, y); y+=rowH;
      ctx.font='bold 14px Arial';
      ctx.fillText('المستخدم', col1, y); ctx.textAlign='right'; ctx.fillText('إجمالي - عدد', col2, y); y+=rowH;
      ctx.strokeStyle='#ddd'; ctx.beginPath(); ctx.moveTo(12,y-14); ctx.lineTo(width-12,y-14); ctx.stroke();
      ctx.font='14px Arial'; ctx.textAlign='left';
      <?php foreach ($summary as $row): $line = addslashes($row['username']); $tot = number_format($row['total_amount'],2); $cnt=(int)$row['sale_count']; ?>
        ctx.fillText('<?= $line ?>', col1, y);
        ctx.textAlign='right'; ctx.fillText('<?= $tot ?> - <?= $cnt ?>', col2, y); ctx.textAlign='left'; y+=rowH;
      <?php endforeach; ?>
      y+=6;
    }

    // Detail table
    if (opts.wantDetails) {
      const cDate=14, cUser=200, cTot=width-14; const rowH=20;
      ctx.textAlign='left'; ctx.font='bold 16px Arial'; ctx.fillText('تفاصيل الفواتير', 14, y); y+=rowH;
      ctx.font='bold 14px Arial';
      ctx.fillText('التاريخ', cDate, y); ctx.fillText('المستخدم', cUser, y); ctx.textAlign='right'; ctx.fillText('الإجمالي', cTot, y); y+=rowH;
      ctx.strokeStyle='#ddd'; ctx.beginPath(); ctx.moveTo(12,y-14); ctx.lineTo(width-12,y-14); ctx.stroke();
      ctx.font='14px Arial'; ctx.textAlign='left';
      <?php foreach ($details as $o): $d=addslashes($o['created_at']); $u=addslashes($o['username']); $t=number_format($o['total'],2); ?>
        ctx.fillText('<?= $d ?>', cDate, y); ctx.fillText('<?= $u ?>', cUser, y); ctx.textAlign='right'; ctx.fillText('<?= $t ?>', cTot, y); ctx.textAlign='left'; y+=rowH;
      <?php endforeach; ?>
    }

    // Footer separator and thank you
    y+=10; ctx.strokeStyle='#000'; ctx.beginPath(); ctx.moveTo(12,y); ctx.lineTo(width-12,y); ctx.stroke(); y+=20;
    ctx.textAlign='center'; ctx.font='bold 14px Arial'; ctx.fillText('— نهاية —', width/2, y);

    return { images:[{ image: canvas.toDataURL('image/png') }] };
  }
</script>

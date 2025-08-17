<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();
if (!Auth::isAdmin() && !Auth::isCashier()) { header('Location: dashboard.php'); exit; }

$from = $_GET['date_from'] ?? date('Y-m-01');
$to   = $_GET['date_to']   ?? date('Y-m-d');

// Aggregate items sold per group in the date range
$stmt = $db->prepare("SELECT g.name AS group_name, i.name_ar AS item_name, SUM(oi.quantity) AS qty, SUM(oi.quantity*oi.unit_price) AS total
FROM Order_Items oi
JOIN Orders o ON o.id = oi.order_id
JOIN Items i ON i.id = oi.item_id
LEFT JOIN Groups g ON g.id = i.group_id
WHERE DATE(o.created_at) BETWEEN :from AND :to
GROUP BY g.name, i.name_ar
ORDER BY g.name, i.name_ar");
$stmt->execute([':from'=>$from, ':to'=>$to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>
<main class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">ملخص المجموعات</h2>
    <form class="row g-2">
      <div class="col-auto"><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
      <div class="col-auto"><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($to) ?>"></div>
      <div class="col-auto"><button class="btn btn-primary">تصفية</button></div>
      <div class="col-auto"><button type="button" id="printSummary" class="btn btn-outline-primary">طباعة كإيصال</button></div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-striped">
      <thead><tr><th>المجموعة</th><th>العنصر</th><th>الكمية</th><th>إجمالي السعر</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['group_name']) ?></td>
            <td><?= htmlspecialchars($r['item_name']) ?></td>
            <td><?= number_format((float)$r['qty'], 2) ?></td>
            <td><?= number_format((float)$r['total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<script>
  document.getElementById('printSummary').addEventListener('click', async () => {
    const rows = Array.from(document.querySelectorAll('table tbody tr')).map(tr => ({
      group: tr.children[0].innerText,
      item: tr.children[1].innerText,
      qty: tr.children[2].innerText,
      total: tr.children[3].innerText,
    }));
    const payload = await buildSummaryImage({ from: '<?= htmlspecialchars($from) ?>', to: '<?= htmlspecialchars($to) ?>', rows });
    const resp = await fetch('../src/print.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await resp.json();
    if (data.success && typeof showToast==='function') showToast('تم إرسال الملخص للطباعة');
  });

  async function buildSummaryImage(opts) {
    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
    const width = 560; canvas.width = width; canvas.height = 1600; let y = 16;
    ctx.fillStyle = '#fff'; ctx.fillRect(0,0,width,canvas.height); ctx.fillStyle='#111'; ctx.textAlign='center'; ctx.font='bold 22px Arial';
    ctx.fillText('ملخص المجموعات', width/2, y); y+=28; ctx.font='14px Arial'; ctx.fillText(`من ${opts.from} إلى ${opts.to}`, width/2, y); y+=22;
    ctx.strokeStyle='#000'; ctx.beginPath(); ctx.moveTo(12,y); ctx.lineTo(width-12,y); ctx.stroke(); y+=12;

    ctx.textAlign='left'; ctx.font='bold 14px Arial'; ctx.fillText('المجموعة | العنصر | الكمية | الإجمالي', 12, y); y+=22; ctx.font='14px Arial';
    opts.rows.forEach(r => { ctx.fillText(`${r.group} | ${r.item} | ${r.qty} | ${r.total}`, 12, y); y+=18; });
    return { images:[{ image: canvas.toDataURL('image/png') }] };
  }
</script>
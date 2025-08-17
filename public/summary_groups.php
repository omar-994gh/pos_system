<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();
if (!Auth::isAdmin() && !Auth::isCashier()) { header('Location: dashboard.php'); exit; }

$from = $_GET['date_from'] ?? date('Y-m-01');
$to   = $_GET['date_to']   ?? date('Y-m-d');
$timeFrom = $_GET['time_from'] ?? '';
$timeTo   = $_GET['time_to']   ?? '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$usersList = $db->query("SELECT id, username FROM Users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT g.name AS group_name, i.name_ar AS item_name, SUM(oi.quantity) AS qty, SUM(oi.quantity*oi.unit_price) AS total
FROM Order_Items oi
JOIN Orders o ON o.id = oi.order_id
JOIN Items i ON i.id = oi.item_id
LEFT JOIN Groups g ON g.id = i.group_id
WHERE DATE(o.created_at) BETWEEN :from AND :to
" . ($userId>0?" AND o.user_id=:uid":"") . (
  ($timeFrom!=='' && $timeTo!=='') ? " AND time(o.created_at) BETWEEN :tfrom AND :tto" : ''
) .
" GROUP BY g.name, i.name_ar ORDER BY g.name, i.name_ar");
$params = [':from'=>$from, ':to'=>$to];
if ($userId>0) $params[':uid'] = $userId;
if ($timeFrom!=='' && $timeTo!=='') { $params[':tfrom'] = $timeFrom; $params[':tto'] = $timeTo; }
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>
<main class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">ملخص المجموعات</h2>
    <form method="get" class="row g-2">
      <div class="col-auto"><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
      <div class="col-auto"><input type="time" name="time_from" class="form-control" value="<?= htmlspecialchars($timeFrom) ?>"></div>
      <div class="col-auto"><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($to) ?>"></div>
      <div class="col-auto"><input type="time" name="time_to" class="form-control" value="<?= htmlspecialchars($timeTo) ?>"></div>
      <div class="col-auto">
        <select name="user_id" class="form-select">
          <option value="0">كل المستخدمين</option>
          <?php foreach($usersList as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $userId===$u['id']?'selected':'' ?>><?= htmlspecialchars($u['username']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto mt-4"><button class="btn btn-primary">تصفية</button></div>
      <div class="col-auto mt-4"><button type="button" id="printSummary" class="btn btn-outline-primary">طباعة كإيصال</button></div>
      <div class="col-auto mt-4"><button type="button" id="exportPDF" class="btn btn-outline-dark">تصدير PDF</button></div>
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
  document.getElementById('exportPDF').addEventListener('click', () => { window.print(); });

  document.getElementById('printSummary').addEventListener('click', async () => {
    const rows = Array.from(document.querySelectorAll('table tbody tr')).map(tr => ({
      group: tr.children[0].innerText,
      item: tr.children[1].innerText,
      qty: tr.children[2].innerText,
      total: tr.children[3].innerText,
    }));
    const payload = await buildSummaryImage({ from: '<?= htmlspecialchars($from) ?>', to: '<?= htmlspecialchars($to) ?>', timeFrom: '<?= htmlspecialchars($timeFrom) ?>', timeTo: '<?= htmlspecialchars($timeTo) ?>', rows });
    const resp = await fetch('../src/print.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await resp.json();
    if (data.success && typeof showToast==='function') showToast('تم إرسال الملخص للطباعة');
  });

  async function buildSummaryImage(opts) {
    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
    const width = 560; canvas.width = width; canvas.height = 1600; let y = 16;
    ctx.fillStyle = '#fff'; ctx.fillRect(0,0,width,canvas.height);

    // Header
    ctx.fillStyle = '#111'; ctx.textAlign='center'; ctx.font = 'bold 22px Arial';
    ctx.fillText('ملخص المجموعات', width/2, y); y+=28;
    ctx.font='14px Arial'; ctx.fillText(`من ${opts.from} ${opts.timeFrom||''} إلى ${opts.to} ${opts.timeTo||''}`, width/2, y); y+=22;
    ctx.strokeStyle='#000'; ctx.beginPath(); ctx.moveTo(12,y); ctx.lineTo(width-12,y); ctx.stroke(); y+=10;

    // Columns
    const cGroup=14, cItem=240, cTot=width-14; const rowH=20;
    ctx.textAlign='left'; ctx.font='bold 14px Arial';
    ctx.fillText('المجموعة', cGroup, y); ctx.fillText('العنصر', cItem, y); ctx.textAlign='right'; ctx.fillText('الكمية | الإجمالي', cTot, y); y+=rowH;
    ctx.strokeStyle='#ddd'; ctx.beginPath(); ctx.moveTo(12,y-14); ctx.lineTo(width-12,y-14); ctx.stroke();

    // Rows
    ctx.font='14px Arial'; ctx.textAlign='left';
    opts.rows.forEach(r => { ctx.fillText(r.group, cGroup, y); ctx.fillText(r.item, cItem, y); ctx.textAlign='right'; ctx.fillText(`${r.qty} | ${r.total}`, cTot, y); ctx.textAlign='left'; y+=rowH; });

    return { images:[{ image: canvas.toDataURL('image/png') }] };
  }
</script>
<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Group.php';
require_once __DIR__ . '/../src/Item.php';

Auth::requireLogin();
if (!Auth::isCashier() && !Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$groupModel = new Group($db);
$itemModel  = new Item($db);

$groups = $groupModel->all();
$settings = $db->query("SELECT tax_rate, currency, font_size_title, font_size_item, font_size_total FROM System_Settings ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$taxRate = $settings['tax_rate'] ?? 0;
$currency = $settings['currency'] ?? 'USD';
$fsTitle = (int)($settings['font_size_title'] ?? 22);
$fsItem  = (int)($settings['font_size_item'] ?? 16);
$fsTotal = (int)($settings['font_size_total'] ?? 18);

// Check privileges
$canEditPrice = Auth::canEditPrice($db);
$canAddDiscount = Auth::canAddDiscount($db);
?>

<?php include 'header.php'; ?>

<style>
.sticky-cart { position: -webkit-sticky; position: sticky; top: 20px; height: calc(100vh - 100px); overflow-y: auto; }
</style>

<div class="row mb-4">
  <a href="market_pos.php" class="btn btn-info">الإدخال اليدوي</a>
</div>

<div class="row">
  <div class="col-md-8">
    <h4>الفئات</h4>
    <ul class="nav nav-tabs" id="groupTabs" role="tablist">
      <?php foreach ($groups as $idx => $g): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $idx===0?'active':'' ?>"
                id="tab-<?= $g['id'] ?>"
                data-bs-toggle="tab"
                data-bs-target="#content-<?= $g['id'] ?>"
                type="button"
                data-group-id="<?= $g['id'] ?>">
          <?= htmlspecialchars($g['name']) ?>
        </button>
      </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content mt-3" id="groupContent">
      <?php foreach ($groups as $idx => $g): ?>
      <div class="tab-pane fade <?= $idx===0?'show active':'' ?>" id="content-<?= $g['id'] ?>">
        <div class="row"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-md-4 sticky-cart">
    <h4>سلة المشتريات</h4>
    <table class="table table-sm" id="cartTable">
      <thead>
        <tr><th>صنف</th><th>كمية</th><th>سعر (<?= $currency ?>)</th><th></th></tr>
      </thead>
      <tbody></tbody>
    </table>
    <div class="mt-3">
      <p>المجموع: <span id="subTotal">0.00</span> <?= $currency ?></p>
      <p>الضريبة (<?= htmlspecialchars($taxRate) ?>%): <span id="taxAmount">0.00</span> <?= $currency ?></p>
      <div class="mb-2">
        <label>الخصم (<?= $currency ?>):</label>
        <input type="number" id="discountInput" class="form-control" value="0" min="0" <?= $canAddDiscount ? '' : 'disabled' ?>>
      </div>
      <h5>الإجمالي: <span id="grandTotal">0.00</span> <?= $currency ?></h5>
      <button id="checkoutBtn" class="btn btn-primary w-100" data-auth="btn_checkout" disabled>إنشاء الطلب</button>
    </div>
  </div>
</div>

</main>
<script src="../assets/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const cart = [];
  const taxRate = parseFloat('<?= $taxRate ?>');
  const currency = '<?= $currency ?>';
  const fsTitle = <?= $fsTitle ?>;
  const fsItem  = <?= $fsItem ?>;
  const fsTotal = <?= $fsTotal ?>;

  function toastOk(msg) { if (typeof showToast === 'function') showToast(msg, 2500); }
  function toastErr(msg) { if (typeof showToast === 'function') showToast(msg, 3500); }

  function renderCart() {
    const tbody = document.querySelector('#cartTable tbody');
    tbody.innerHTML = '';
    let subTotal = 0;
    cart.forEach((item, idx) => {
      subTotal += item.quantity * item.unit_price;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${item.name}</td>
        <td><input type="number" class="form-control form-control-sm qty-input" data-idx="${idx}" min="1" value="${item.quantity}"></td>
        <td><input type="number" class="form-control form-control-sm price-input" data-idx="${idx}" step="0.01" value="${item.unit_price}" data-auth="input_edit_price" ${<?= $canEditPrice ? 'true' : 'false' ?> ? '' : 'disabled' }></td>
        <td><span class="item-total">${(item.quantity * item.unit_price).toFixed(2)}</span></td>
        <td><button class="btn btn-sm btn-danger remove" data-idx="${idx}">×</button></td>`;
      tbody.appendChild(tr);
      
      // Add event listeners for quantity and price inputs
      tr.querySelector('.qty-input').addEventListener('input', e => { 
        const i = +e.target.dataset.idx; 
        cart[i].quantity = Math.max(1, parseFloat(e.target.value)||1); 
        recalcTotals(); 
      });
      
      const priceInput = tr.querySelector('.price-input');
      if (!priceInput.disabled) {
        priceInput.addEventListener('input', e => { 
          const i = +e.target.dataset.idx; 
          cart[i].unit_price = Math.max(0, parseFloat(e.target.value)||0); 
          recalcTotals(); 
        });
      }
    });
    
    recalcTotals();
  }

  function recalcTotals() {
    let subTotal = 0;
    document.querySelectorAll('#cartTable tbody tr').forEach(tr => {
      const qty = Math.max(1, parseFloat(tr.querySelector('.qty-input').value)||1);
      const priceField = tr.querySelector('.price-input');
      const price = Math.max(0, parseFloat(priceField.value)||0);
      const lineTot = qty * price;
      subTotal += lineTot;
      tr.querySelector('.item-total').textContent = lineTot.toFixed(2);
    });
    
    const discount = Math.max(0, parseFloat(document.getElementById('discountInput').value) || 0);
    const taxAmount = subTotal * taxRate / 100;
    const grandTotal = subTotal + taxAmount - discount;
    
    document.getElementById('subTotal').textContent = subTotal.toFixed(2);
    document.getElementById('taxAmount').textContent = taxAmount.toFixed(2);
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
    document.getElementById('checkoutBtn').disabled = cart.length === 0;
  }

  function handleAddToCart(e) {
    const btn = e.target.closest('.add-to-cart');
    if (!btn) return;
    if (btn.hasAttribute('disabled')) return;
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    const name_en = btn.dataset.namen;
    const price = parseFloat(btn.dataset.price);
    const groupId = parseInt(btn.dataset.groupId);
    const qtyInput = btn.parentElement.querySelector('.qty-input');
    const quantity = Math.max(1, parseFloat(qtyInput.value) || 1);
    const existingIndex = cart.findIndex(it => String(it.item_id) === String(id));
    if (existingIndex !== -1) { cart[existingIndex].quantity += quantity; }
    else { cart.push({ item_id: id, name, name_en, unit_price: price, quantity, group_id: groupId }); }
    renderCart();
    toastOk('تمت الإضافة إلى السلة');
  }
  document.addEventListener('click', handleAddToCart);

  document.querySelector('#cartTable').addEventListener('click', e => {
    if (e.target.classList.contains('remove')) {
      const idx = parseInt(e.target.dataset.idx);
      cart.splice(idx, 1);
      renderCart();
    }
  });

  document.getElementById('checkoutBtn').addEventListener('click', completeSale);
  
  // Add discount input event listener
  document.getElementById('discountInput').addEventListener('input', recalcTotals);

  async function completeSale() {
    if (cart.length === 0) { toastErr('السلة فارغة'); return; }
    const subTotal = cart.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);
    const taxAmount = subTotal * taxRate / 100;
    const discount = Math.max(0, parseFloat(document.getElementById('discountInput').value) || 0);
    const total = subTotal + taxAmount - discount;

    const response = await fetch('pos_handler.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: cart, total })
    });
    const result = await response.json();
    if (!result.success) { toastErr('فشل في إنشاء الطلب'); return; }

    const itemsByGroup = {};
    cart.forEach(it => { const gid = parseInt(it.group_id || 0); if (!itemsByGroup[gid]) itemsByGroup[gid] = []; itemsByGroup[gid].push(it); });

    const groupPrinters = result.groupPrinters || {};
    const unassignedPrinters = result.unassignedPrinters || [];
    const images = [];
    for (const [gidStr, items] of Object.entries(itemsByGroup)) {
      const gid = parseInt(gidStr);
      const printerId = parseInt(groupPrinters[gid] || 0);
      const img = await generateInvoiceImage(items, subTotal, taxAmount, total, result.orderSeq, fsTitle, fsItem, fsTotal);
      images.push({ image: img, printer_ids: printerId ? [printerId] : [] });
    }
    if (unassignedPrinters.length) {
      const fullImg = await generateInvoiceImage(cart, subTotal, taxAmount, total, result.orderSeq, fsTitle, fsItem, fsTotal);
      images.push({ image: fullImg, printer_ids: unassignedPrinters });
    }

    const printResp = await fetch('../src/print.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ images, order_id: result.orderId }) });
    const printResult = await printResp.json();
    if (printResult.success) { 
      toastOk('تم إنشاء الطلب وطباعة الفاتورة'); 
      cart.length = 0; 
      renderCart(); 
    } else { 
      toastErr('تم البيع ولكن فشلت الطباعة'); 
      // Clear cart and show error message even when printing fails
      cart.length = 0; 
      renderCart(); 
    }
  }

  async function generateInvoiceImage(items, subTotal, taxAmount, total, orderSeq, fsTitle, fsItem, fsTotal) {
    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
    const settings = await fetch('get_print_settings.php').then(r => r.json()); const cfg = {}; settings.forEach(s => cfg[s.key] = s);
    const widthMm = parseInt(cfg['print_width_mm']?.value) || 80; const pxPerMm = 7; const width = widthMm * pxPerMm;

    const infoLines = [];
    if (cfg['field_restaurant_name']?.value == 1) infoLines.push(cfg['restaurant_name']?.value || '');
    if (cfg['field_username']?.value == 1) infoLines.push('المستخدم: ' + '<?= htmlspecialchars($_SESSION["username"]) ?>');
    if (cfg['field_tax_number']?.value == 1) infoLines.push('الرقم الضريبي: ' + (cfg['tax_number']?.value || ''));
    infoLines.push(cfg['address']?.value || '');

    const rowH=45, headerH=100, infoH=infoLines.length*25, tableH=(items.length+1)*rowH, footerH=110, extra=270;
    canvas.width = width; canvas.height = headerH + infoH + tableH + footerH + extra;

    ctx.fillStyle = 'white'; ctx.fillRect(0, 0, width, canvas.height);

    let y = 10;
    if (cfg['field_restaurant_logo']?.value == 1 && cfg['logo_path']?.value) {
      const logo = new Image(); logo.src = 'images/logo.png'; await new Promise(r => logo.onload = r);
      const logoW = 200; const logoH = (logo.height * logoW) / logo.width; ctx.drawImage(logo, (width - logoW) / 2, y, logoW, logoH); y += logoH + 20;
    }

    ctx.fillStyle = 'black'; ctx.textAlign = 'center'; ctx.font = `${Math.max(16, fsTitle)}px Arial`;
    infoLines.forEach(line => { ctx.fillText(line, width / 2, y); y += 25; });

    ctx.font = `bold ${Math.max(16, fsTitle)}px Arial`; ctx.textAlign = 'right'; ctx.fillStyle = '#111';
    ctx.fillText(`رقم الطلب: ${orderSeq}`, width - 20, y + 40); y += 60;

    const cols = ['اسم', 'كمية', 'سعر إفرادي', 'سعر إجمالي']; const colW = [width * 0.4, width * 0.18, width * 0.2, width * 0.22];
    ctx.font = `bold ${Math.max(12, fsItem)}px Arial`; ctx.textAlign = 'center';
    let x = width; cols.forEach((title, i) => { x -= colW[i]; ctx.strokeStyle = '#ccc'; ctx.strokeRect(x + 4, y, colW[i] - 8, rowH); ctx.fillText(title, x + colW[i] / 2, y + rowH / 2); }); y += rowH;

    ctx.font = `bold ${Math.max(12, fsItem)}px Arial`; ctx.fillStyle = '#000';
    items.forEach(item => { x = width; const nameLines = [item.name]; if (item.name_en) nameLines.push(item.name_en);
      const qty  = item.quantity; const price= item.unit_price; const tot  = (qty * item.unit_price);
      const cells = [nameLines.join('\n'), qty, price.toFixed(2), tot.toFixed(2)];
      cells.forEach((txt, i) => { x -= colW[i]; ctx.strokeStyle = '#eee'; ctx.strokeRect(x + 4, y, colW[i] - 8, rowH);
        if (i === 0 && String(txt).includes('\n')) { String(txt).split('\n').forEach((ln, idx) => { ctx.fillText(ln, x + colW[i] / 2, y + 18 + idx * 18); }); }
        else { ctx.fillText(txt, x + colW[i] / 2, y + rowH / 2); } }); y += rowH; });

    y += 20; ctx.beginPath(); ctx.moveTo(20, y); ctx.lineTo(width - 20, y); ctx.strokeStyle = '#000'; ctx.lineWidth = 1; ctx.stroke(); y += 30;

    ctx.font = `bold ${Math.max(12, fsTotal)}px Arial`; ctx.textAlign = 'right'; ctx.fillStyle = '#111';
    ctx.fillText(`المجموع: ${subTotal.toFixed(2)}`, width - 4, y + 20);
    ctx.fillText(`الضريبة: ${taxAmount.toFixed(2)}`, width - 4, y + 45);
    ctx.fillText(`الإجمالي: ${total.toFixed(2)}`, width - 4, y + 70);

    ctx.textAlign = 'center'; ctx.font = `bold ${Math.max(12, fsItem)}px Arial`; ctx.fillText('شكراً لزيارتكم', width/2, y + 110);

    return canvas.toDataURL('image/png');
  }

  const loadedGroups = new Set();
  const loadInitialContent = async () => {
    const firstTab = document.querySelector('#groupTabs .nav-link.active');
    if (firstTab) { await loadGroup(firstTab); }
  };

  async function loadGroup(tabEl) {
    const groupId = tabEl.dataset.groupId;
    const targetPane = document.querySelector(tabEl.dataset.bsTarget);
    if (!loadedGroups.has(groupId)) {
      await fetchItems(groupId, targetPane); loadedGroups.add(groupId);
    }
  }

  const fetchItems = async (groupId, targetPane) => {
    try {
      const response = await fetch(`get_items.php?group_id=${groupId}`);
      const items = await response.json();
      const itemsHTML = items.map(item => 
        `<div class="col-sm-6 col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">${item.name_ar}</h5>
              <p class="card-text">السعر: ${item.price} ${currency}</p>
              <p class="card-text">الكمية في المستودع: ${item.stock}</p>
              <div class="mt-auto">
                <input type="number" class="form-control mb-2 qty-input" placeholder="الكمية" min="1" value="1" data-auth="qty_input" ${item.stock <= 0 ? 'disabled' : ''}>
                <button ${item.stock <= 0 ? 'disabled' : ''} class="btn btn-${item.stock <= 0 ? 'danger' : 'success'} w-100 add-to-cart"
                        data-id="${item.id}"
                        data-name="${item.name_ar}"
                        data-namen="${item.name_en}"
                        data-price="${item.price}"
                        data-group-id="${item.group_id}">
                  ${item.stock <= 0 ? 'غير متاح' : 'إضافة للسلة'}
                </button>
              </div>
            </div>
          </div>
        </div>`
      ).join('');
      targetPane.querySelector('.row').innerHTML = itemsHTML;
    } catch (error) { toastErr('فشل في تحميل الأصناف'); }
  };

  loadInitialContent();
  document.querySelectorAll('#groupTabs button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', async (event) => { await loadGroup(event.target); });
    tab.addEventListener('click', async (event) => { await loadGroup(event.currentTarget); });
  });

  // Manual fallback activation in case Bootstrap events don’t switch panes
  document.getElementById('groupTabs').addEventListener('click', async (e) => {
    const btn = e.target.closest('button.nav-link');
    if (!btn) return;
    e.preventDefault();
    document.querySelectorAll('#groupTabs .nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const targetSel = btn.getAttribute('data-bs-target');
    document.querySelectorAll('.tab-content .tab-pane').forEach(p => p.classList.remove('show','active'));
    const pane = document.querySelector(targetSel);
    if (pane) { pane.classList.add('show','active'); await loadGroup(btn); }
  });
});
</script>
</body>
</html>
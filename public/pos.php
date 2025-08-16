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

// Load groups
$groups = $groupModel->all();
// Load settings
$settings = $db->query("SELECT tax_rate, currency FROM System_Settings ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$taxRate = $settings['tax_rate'] ?? 0;
$currency = $settings['currency'] ?? 'USD';
?>

<?php include 'header.php'; ?>

<style>
    .sticky-cart {
        position: -webkit-sticky;
        position: sticky;
        top: 20px;
        height: calc(100vh - 100px);
        overflow-y: auto;
    }
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
        <div class="row" id="items-<?= $g['id'] ?>">
          <?php
            // REMOVED THE IF($idx === 0) CONDITION
            // Now, initially load items for ALL groups or rely solely on JS for all groups
            // For now, we will let JS load all of them.
            // If you wanted to pre-load all, you would fetch all items here.
            // But since your JS already handles dynamic loading, we'll let it do the work.
          ?>
        </div>
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
      <h5>الإجمالي: <span id="grandTotal">0.00</span> <?= $currency ?></h5>
      <button id="checkoutBtn" class="btn btn-primary w-100" disabled>إنشاء الطلب</button>
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

  function renderCart() {
    const tbody = document.querySelector('#cartTable tbody');
    tbody.innerHTML = '';
    let subTotal = 0;
    cart.forEach((item, idx) => {
      subTotal += item.quantity * item.unit_price;
      const tr = document.createElement('tr');
      tr.innerHTML = 
        `<td>${item.name}</td>
         <td>${item.quantity}</td>
         <td>${(item.quantity * item.unit_price)}</td>
         <td><button class="btn btn-sm btn-danger remove" data-idx="${idx}">×</button></td>`;
      tbody.appendChild(tr);
    });
    document.getElementById('subTotal').textContent = subTotal;
    const taxAmount = subTotal * taxRate / 100;
    document.getElementById('taxAmount').textContent = taxAmount;
    const grandTotal = subTotal + taxAmount;
    document.getElementById('grandTotal').textContent = grandTotal;
    document.getElementById('checkoutBtn').disabled = cart.length === 0;
  }

  // Improved event handling
  function handleAddToCart(e) {
    const btn = e.target.closest('.add-to-cart');
    if (!btn) return;
    
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    const name_en = btn.dataset.namen;
    const price = parseFloat(btn.dataset.price);
    const qtyInput = btn.parentElement.querySelector('.qty-input');
    const quantity = parseFloat(qtyInput.value) || 1;
    console.log(name_en);
    // Check for existing item
    const existingIndex = cart.findIndex(item => item.item_id === id); // Fix: use findIndex
    if (existingIndex !== -1) { // Fix: check if item exists
      cart[existingIndex].quantity += quantity; // Fix: add to existing quantity
    } else {
      cart.push({ item_id: id, name, name_en, unit_price: price, quantity });
    }
    
    renderCart();
  }

  // Single event listener for all add-to-cart buttons
  document.addEventListener('click', handleAddToCart);

  // Remove from cart
  document.querySelector('#cartTable').addEventListener('click', e => {
    if (e.target.classList.contains('remove')) {
      const idx = parseInt(e.target.dataset.idx);
      cart.splice(idx, 1);
      renderCart();
    }
  });

  // Attach completeSale to checkout button
  document.getElementById('checkoutBtn').addEventListener('click', completeSale);

  // Main handler
  async function completeSale() {
    if (cart.length === 0) {
      alert('السلة فارغة، أضف أصناف أولاً');
      return;
    }

    // حساب الإجمالي
    const subTotal = cart.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);
    const taxAmount = subTotal * taxRate / 100;
    const total = subTotal + taxAmount;

    // إنشاء الطلب في الباك اند
    const response = await fetch('pos_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: cart, total }),
    });
    const result = await response.json();
    if (!result.success) {
      alert('فشل في إنشاء الطلب: ' + (result.error||''));
      return;
    }
    const { orderId, orderSeq } = result;
    // ثم:
    const imageData = await generateInvoiceImage(cart, subTotal, taxAmount, total, orderSeq);
    // console.log(imageData);
    // إرسال الصورة للطباعة 
    const printResp = await fetch('../src/print.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ image: imageData, order_id: result.orderId }),
    });
    const printResult = await printResp.json();
    if (printResult.success) {
      alert('تم إنشاء الطلب وطباعة الفاتورة بنجاح');
      cart.length = 0;
      renderCart();
    } else {
      alert('تم إنشاء الطلب ولكن فشل في الطباعة: ' + (printResult.error || ''));
    }
  }

  async function generateInvoiceImage(items, subTotal, taxAmount, total, orderSeq) {
  // 1. canvas و ctx
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  // 2. جلب الإعدادات
  const settings = await fetch('get_print_settings.php')
    .then(r => r.json());

  // 3. بناء خريطة من الإعدادات
  const cfg = {};
  settings.forEach(s => cfg[s.key] = s);

  // 4. حساب العرض: 4px لكل ميليمتر
  const widthMm = parseInt(cfg['print_width_mm']?.value) || 80;
  const pxPerMm = 7;
  const width   = widthMm * pxPerMm;

  // 5. جمع infoLines للمفاتيح
  const infoLines = [];
  if (cfg['field_restaurant_name']?.value == 1)
    infoLines.push(cfg['restaurant_name']?.value || '');
  if (cfg['field_username']?.value == 1)
    infoLines.push('المستخدم: ' + '<?= htmlspecialchars($_SESSION["username"]) ?>');
  if (cfg['field_tax_number']?.value == 1)
    infoLines.push('الرقم الضريبي: ' + (cfg['tax_number']?.value || ''));
  // عنوان
  infoLines.push(cfg['address']?.value || '');

  // 6. الأبعاد الرأسية
  const rowH    = 45;
  const headerH = 100;
  const infoH   = infoLines.length * 25;
  const tableH  = (items.length + 1) * rowH;
  const footerH = 80;
  const extra  = 270;

  canvas.width  = width;
  canvas.height = headerH + infoH + tableH + footerH + extra;

  // 7. الخلفية
  ctx.fillStyle = 'white';
  ctx.fillRect(0, 0, width, canvas.height);

  // 8. الشعار إذا مفعل
  let y = 10;
  if (cfg['field_restaurant_logo']?.value == 1 && cfg['logo_path']?.value) {
    const logo = new Image();
    logo.src = 'images/logo.png';
    await new Promise(r => logo.onload = r);
    const logoW = 200;
    const logoH = (logo.height * logoW) / logo.width;
    ctx.drawImage(logo, (width - logoW) / 2, y, logoW, logoH);
    y += logoH + 20;
  }

  // 9. رسم infoLines
  ctx.fillStyle = 'black';
  ctx.font = '18px Arial';
  ctx.textAlign = 'center';
  infoLines.forEach(line => {
    ctx.fillText(line, width / 2, y);
    y += 25;
  });

  ctx.font = 'bold 22px Arial'; // حجم الخط
  ctx.textAlign = 'right'; // محاذاة لليمين
  ctx.fillStyle = '#333'; // لون داكن

  // نص واحد يجمع التسمية والرقم
  const orderText = `رقم الطلب: ${orderSeq}`; 
  ctx.fillText(orderText, width - 20, y + 40); // (x, y)

  // إضافة فراغ 20px قبل الجدول
  y += 60; 

  // 10. رؤوس الجدول RTL
  const cols = ['اسم', 'كمية', 'سعر إفرادي', 'سعر إجمالي'];
  const colW = [width * 0.4, width * 0.2, width * 0.2, width * 0.2];
  ctx.font = 'bold 18px Arial';
  ctx.textAlign = 'center';
  let x = width;
  cols.forEach((title, i) => {
    x -= colW[i];
    ctx.strokeRect(x + 4, y, colW[i] - 8, rowH);
    ctx.fillText(title, x + colW[i] / 2, y + rowH / 2);
  });
  y += rowH;

  // 11. صفوف البيانات RTL
  ctx.font = 'bold 16px Arial';
  items.forEach(item => {
    x = width;
    // بناء نص الاسم (عربي وأسفله إنجليزي)
    let nameLines = [];
    if (cfg['field_item_name_ar']?.value == 1) nameLines.push(item.name);
    if (cfg['field_item_name_en']?.value == 1) {
        // تحقق من وجود القيمة وطباعتها
        console.log('Item EN Name:', item.name_en || 'غير موجود');
        if (item.name_en) {
            nameLines.push(item.name_en);
        }
    }
    const qty  = item.quantity;
    const price= item.unit_price;
    const tot  = (qty * item.unit_price);

    // مصفوفة القيم بالترتيب نفسه
    const cells = [nameLines.join('\n'), qty, price, tot];
    cells.forEach((txt, i) => {
      x -= colW[i];
      ctx.strokeRect(x + 4, y, colW[i] - 8, rowH);
      if (i === 0 && txt.includes('\n')) {
        txt.split('\n').forEach((ln, idx) => {
          ctx.fillText(ln, x + colW[i] / 2, y + 18 + idx * 18);
        });
      } else {
        ctx.fillText(txt, x + colW[i] / 2, y + rowH / 2);
      }
    });
    y += rowH;
  });

  // 12. الإجماليات
  y += 20; // إضافة مسافة قبل الخط
  
  // رسم خط أفقي
  ctx.beginPath();
  ctx.moveTo(20, y); // بداية الخط من اليسار بمقدار 20px
  ctx.lineTo(width - 20, y); // نهاية الخط إلى اليمين بمقدار 20px
  ctx.strokeStyle = '#000'; // لون الخط
  ctx.lineWidth = 1; // سماكة الخط
  ctx.stroke();
  
  y += 30; // زيادة المسافة بعد الخط
  
  ctx.font = 'bold 18px Arial';
  ctx.textAlign = 'right';
  ctx.fillText(`المجموع: ${subTotal}`, width - 4, y + 20);
  ctx.fillText(`الضريبة: ${taxAmount}`, width - 4, y + 45);
  ctx.fillText(`الإجمالي: ${total}`, width - 4, y + 70);

  return canvas.toDataURL('image/png');
}

// تحميل المحتوى الأولي للمجموعة الأولى
  const loadInitialContent = async () => {
    const firstTab = document.querySelector('#groupTabs .nav-link.active');
    if (firstTab) {
      const targetPane = document.querySelector(firstTab.dataset.bsTarget);
      // Removed the check for children.length === 0 because PHP no longer pre-loads
      await fetchItems(firstTab.dataset.groupId, targetPane);
      // No need to call attachAddToCartEvents here, as handleAddToCart uses event delegation.
    }
  };

  // دالة تحميل الأصناف
  const fetchItems = async (groupId, targetPane) => {
    try {
      const response = await fetch(`get_items.php?group_id=${groupId}`);
      const items = await response.json();
      console.log('Items Data:', items);
      const itemsHTML = items.map(item => 
        `<div class="col-sm-6 col-md-4 mb-4">
          <div class="card h-100">
            <img class="card-img-top" src="images/default-item.png" alt="${item.name_ar}">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">${item.name_ar}</h5>
              <p class="card-text">السعر: ${item.price} ${currency}</p>
              <p class="card-text">الكمية في المستودع: ${item.stock}</p>
              <div class="mt-auto">
                <input type="number" class="form-control mb-2 qty-input" 
                       placeholder="الكمية" min="1" value="1">
                <button ${item.stock == 0 ? 'disabled' : ''} class="btn btn-${item.stock == 0 ? 'danger' : 'success'} w-100 add-to-cart"
                         data-id="${item.id}"
                         data-name="${item.name_ar}"
                         data-namen="${item.name_en}"
                         data-price="${item.price}">
                  إضافة للسلة
                </button>
              </div>
            </div>
          </div>
        </div>`
      ).join('');
      
      targetPane.querySelector('.row').innerHTML = itemsHTML;
    } catch (error) {
      console.error('فشل في تحميل الأصناف:', error);
    }
  };

  // تحميل المحتوى الأولي عند البدء
  loadInitialContent();

  // بقية الأحداث
  document.querySelectorAll('#groupTabs button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', async (event) => {
      const groupId = event.target.dataset.groupId;
      const targetPane = document.querySelector(event.target.dataset.bsTarget);
      await fetchItems(groupId, targetPane);
      // No need to call attachAddToCartEvents here, as handleAddToCart uses event delegation.
    });
  });
});
</script>
</body>
</html>
<?php
// public/pos_barcode.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();
if (!Auth::isCashier() && !Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}
// جلب إعدادات الضريبة والعملة
$setting  = $db->query("
    SELECT tax_rate, currency 
      FROM System_Settings 
     ORDER BY id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
$taxRate  = floatval($setting['tax_rate'] ?? 0);
$currency = htmlspecialchars($setting['currency'] ?? 'USD');
?>
<?php include 'header.php'; ?>

<style>
  /* بطاقة بيانات الصنف */
  #itemCard {
    display: none;
    animation: fadeIn 0.4s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px) }
    to   { opacity: 1; transform: translateY(0) }
  }
  .sticky-cart {
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
  }
  .qty-input, .price-input {
    width: 4rem;
    display: inline-block;
  }
</style>

<div class="container-fluid">
  <!-- صف البحث يحتل العرض الكامل -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="input-group">
        <span class="input-group-text">║▌║█║▌│</span>
        <input type="text" id="barcodeInput" 
               class="form-control form-control-lg w-100" 
               placeholder="أدخل باركود المادة واضغط Enter">
      </div>
    </div>
  </div>

  <!-- صف السلة يحتل العرض الكامل تحت البحث -->
  <div class="row">
    <div class="col-12">
      <div class="sticky-cart bg-white p-3 shadow-sm">
        <h4><i class="bi bi-cart-fill"></i> سلة المشتريات</h4>
        <table class="table table-md mb-2" id="cartTable">
          <thead>
            <tr>
              <th>صنف</th>
              <th>كمية</th>
              <th>سعر إفرادي(<?= $currency ?>)</th>
              <th>سعر إجمالي</th>
              <th>إلغاء العنصر</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <p>المجموع: <span id="subTotal">0.00</span> <?= $currency ?></p>
        <p>الضريبة (<?= $taxRate ?>%): <span id="taxAmount">0.00</span></p>
        <div class="mb-2">
          <label>الخصم (<?= $currency ?>):</label>
          <input type="number" id="discountInput" class="form-control" value="0" min="0">
        </div>
        <h5>الإجمالي: <span id="grandTotal">0.00</span> <?= $currency ?></h5>
        <button id="checkoutBtn" class="btn btn-success w-100" disabled>
          <i class="bi bi-check2-circle"></i> إنهاء وبيع
        </button>
      </div>
    </div>
  </div>
</div>

</main>
<script src="../assets/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const cart = [];
  const taxRate = <?= $taxRate ?>;
  const currency = '<?= $currency ?>';

  const barcodeInput   = document.getElementById('barcodeInput');
  const itemCard       = document.getElementById('itemCard');
  const itemImage      = document.getElementById('itemImage');
  const itemName       = document.getElementById('itemName');
  const itemBarcode    = document.getElementById('itemBarcode');
  const itemPrice      = document.getElementById('itemPrice');
  const itemQty        = document.getElementById('itemQty');
  const addBtn         = document.getElementById('addBtn');
  const discountInput  = document.getElementById('discountInput');

  function renderCart() {
    const tbody = document.querySelector('#cartTable tbody');
    tbody.innerHTML = '';
    let subTotal = 0;

    cart.forEach((it, idx) => {
      const lineTotal = it.quantity * it.unit_price;
      subTotal += lineTotal;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.name}</td>
        <td>
          <input type="number" class="form-control form-control qty-input w-100" 
                 data-idx="${idx}" min="1" value="${it.quantity}">
        </td>
        <td> 
          <input type="number" class="form-control form-control price-input w-100" 
                 data-idx="${idx}" step="0.01" value="${it.unit_price}">
        </td>
        <td><span class="item-total">${lineTotal}</span></td>
        <td>
          <button class="btn btn-sm btn-danger remove" data-idx="${idx}">×</button>
        </td>
      `;
      tbody.appendChild(tr);

      // حدث تعديل الكمية
      tr.querySelector('.qty-input').addEventListener('input', e => {
        const i = +e.target.dataset.idx;
        cart[i].quantity = parseFloat(e.target.value) || 1;
        // renderCart();
      });
      // حدث تعديل السعر
      tr.querySelector('.price-input').addEventListener('input', e => {
        const i = +e.target.dataset.idx;
        cart[i].unit_price = parseFloat(e.target.value) || 0;
        // renderCart();
      });
    });

    const discount = parseFloat(discountInput.value) || 0;
    const taxAmount = subTotal * taxRate / 100;
    const grandTotal = subTotal + taxAmount - discount;

    document.getElementById('subTotal').textContent   = subTotal.toFixed(2);
    document.getElementById('taxAmount').textContent  = taxAmount.toFixed(2);
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
    document.getElementById('checkoutBtn').disabled   = cart.length === 0;
  }

  function recalcTotals() {
    let subTotal = 0;
    document.querySelectorAll('#cartTable tbody tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty-input').value) || 1;
      const price = parseFloat(tr.querySelector('.price-input').value) || 0;
      const lineTot = qty * price;
      subTotal += lineTot;
      tr.querySelector('.item-total').textContent = lineTot.toFixed(2);
    });
    const discount = parseFloat(discountInput.value) || 0;
    const taxAmount = subTotal * taxRate / 100;
    document.getElementById('subTotal').textContent   = subTotal.toFixed(2);
    document.getElementById('taxAmount').textContent  = taxAmount.toFixed(2);
    document.getElementById('grandTotal').textContent = (subTotal + taxAmount - discount).toFixed(2);
  }

  // تفويض للـ quantity و price inputs
  document.querySelector('#cartTable tbody').addEventListener('input', e => {
    if (e.target.matches('.qty-input') || e.target.matches('.price-input')) {
      const idx = +e.target.dataset.idx;
      // حدّثي البيانات في المصفوفة
      if (e.target.matches('.qty-input')) {
        cart[idx].quantity = parseFloat(e.target.value) || 1;
      } else {
        cart[idx].unit_price = parseFloat(e.target.value) || 0;
      }
      // أعدي حساب المجاميع فقط
      recalcTotals();
    }
  });



  // إزالة عنصر
  document.querySelector('#cartTable').addEventListener('click', e => {
    if (e.target.classList.contains('remove')) {
      const idx = +e.target.dataset.idx;
      cart.splice(idx, 1);
      renderCart();
    }
  });

  // بحث بالباركود عند Enter
  barcodeInput.addEventListener('keydown', async e => {
    if (e.key !== 'Enter' || !barcodeInput.value.trim()) return;
    e.preventDefault();
    const code = encodeURIComponent(barcodeInput.value.trim());
    const res  = await fetch(`items_by_barcode.php?barcode=${code}`);
    const it   = await res.json();
    if (!it.id) {
      alert('لم أجد مادة بهذا الباركود');
      barcodeInput.select();
      return;
    }
    // إضافة مباشر للسلة
    const existing = cart.find(i => i.item_id == it.id);
    if (existing) {
      existing.quantity += 1;
    } else {
      cart.push({
        item_id: it.id,
        name: it.name_ar + (it.name_en ? ` / ${it.name_en}` : ''),
        unit_price: parseFloat(it.price),
        quantity: 1
      });
    }
    renderCart();
    barcodeInput.value = '';
    //barcodeInput.focus();
  });

  // إضافة من البطاقة (اختياري)
  addBtn.addEventListener('click', () => {
    const id = addBtn.dataset.id;
    const qty = parseFloat(itemQty.value) || 1;
    const existing = cart.find(i => i.item_id == id);
    if (existing) {
      existing.quantity += qty;
    } else {
      cart.push({
        item_id: id,
        name: itemName.textContent,
        unit_price: parseFloat(addBtn.dataset.price),
        quantity: qty
      });
    }
    renderCart();
    barcodeInput.value = '';
    itemCard.style.display = 'none';
    //barcodeInput.focus();
  });

  // إعادة حساب عند تغيير الخصم
  discountInput.addEventListener('input', renderCart);

  // إنهاء البيع
  document.getElementById('checkoutBtn').addEventListener('click', async () => {
    if (!cart.length) return alert('السلة فارغة');
    const subTotal = cart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
    const taxAmount = subTotal * taxRate / 100;
    const discount = parseFloat(discountInput.value) || 0;
    const total = subTotal + taxAmount - discount;

    const resp = await fetch('pos_handler.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ items: cart, total })
    });
    const result = await resp.json();
    if (!result.success) {
      return alert('فشل في إنشاء الطلب: ' + (result.error||''));
    }
    const { orderSeq, orderId } = result;
    const imageData = await generateInvoiceImage(cart, subTotal, taxAmount, total, orderSeq, discount);
    const printResp = await fetch('../src/print.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ image:imageData, order_id:orderId })
    });
    const printResult = await printResp.json();
    if (printResult.success) {
      alert('تم البيع وطباعة الفاتورة بنجاح');
      cart.length = 0;
      renderCart();
    } else {
      alert('تم البيع ولكن فشل الطباعة: '+(printResult.error||''));
    }
  });

  // حافظ على التركيز
  // //barcodeInput.focus();
});
</script>

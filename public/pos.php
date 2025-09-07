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
  <div class="col-md-7">
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

  <div class="col-md-5 sticky-cart">
    <h4>سلة المشتريات</h4>
    <table class="table" id="cartTable">
      <thead>
        <tr><th>صنف</th><th>كمية</th><th>سعر إفرادي(<?= $currency ?>)</th><th>سعر إجمالي</th><th>إلغاء الصنف</th></tr>
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

<div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg"> <div class="modal-content">
      <div class="modal-header bg-primary text-white"> <h5 class="modal-title" id="notesModalLabel">إضافة ملاحظات للصنف: <span id="modalItemName"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close">X</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="modalItemId">
        <input type="hidden" id="modalItemNameAr">
        <input type="hidden" id="modalItemNameEn">
        <input type="hidden" id="modalItemPrice">
        <input type="hidden" id="modalItemQty">
        <input type="hidden" id="modalItemGroupId">

        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-light">
            <h6 class="mb-0">إضافة ملاحظة جديدة</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="newNoteAr" class="form-label">نص الملاحظة (عربي):</label>
              <input type="text" class="form-control" id="newNoteAr" placeholder="اكتب ملاحظة جديدة بالعربي">
            </div>
            <div hidden class="mb-3">
              <label for="newNoteEn" class="form-label">نص الملاحظة (إنجليزي - اختياري):</label>
              <input type="text" class="form-control" id="newNoteEn" placeholder="اكتب ملاحظة جديدة بالإنجليزي (اختياري)">
            </div>
            <button class="btn btn-outline-primary w-100" id="saveNewNoteBtn">حفظ وإضافة للملاحظات المتاحة</button>
          </div>
        </div>

        <div hidden class="card mb-4 shadow-sm">
          <div class="card-header bg-light">
            <h6 class="mb-0">الملاحظات التي سيتم إضافتها للصنف:</h6>
          </div>
          <div class="card-body" id="selectedNotesDisplay">
              <p class="text-muted text-center mb-0">لا توجد ملاحظات مختارة بعد.</p>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0">اختر من الملاحظات الموجودة:</h6>
            <input hidden type="text" id="noteSearchInput" class="form-control form-control-sm w-50" placeholder="بحث عن ملاحظة...">
          </div>
          <div class="card-body notes-grid-area" style="max-height: 280px; overflow-y: auto;"> <div id="availableNotesList" class="row row-cols-auto g-2 justify-content-between"> <div class="col text-center text-muted p-3 w-100">جاري تحميل الملاحظات...</div>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2 ml-2"></i>إلغاء</button>
        <button type="button" class="btn btn-success" id="addNotesToCartBtn"><i class="fas fa-plus me-2"></i>إضافة للسلة</button>
      </div>
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
  const canEditPrice = <?= $canEditPrice ? 'true' : 'false' ?>;
  const canAddDiscount = <?= $canAddDiscount ? 'true' : 'false' ?>;
  
  // متغير لتخزين الملاحظات المؤقتة قبل إضافتها للسلة
  let currentSelectedNotes = [];

    // Modal elements
  const notesModal = new bootstrap.Modal(document.getElementById('notesModal'));
  const modalItemName = document.getElementById('modalItemName');
  const modalItemId = document.getElementById('modalItemId');
  const modalItemNameAr = document.getElementById('modalItemNameAr');
  const modalItemNameEn = document.getElementById('modalItemNameEn');
  const modalItemPrice = document.getElementById('modalItemPrice');
  const modalItemQty = document.getElementById('modalItemQty');
  const modalItemGroupId = document.getElementById('modalItemGroupId');
  const availableNotesList = document.getElementById('availableNotesList');
  const selectedNotesDisplay = document.getElementById('selectedNotesDisplay');
  const newNoteArInput = document.getElementById('newNoteAr');
  const newNoteEnInput = document.getElementById('newNoteEn');
  const saveNewNoteBtn = document.getElementById('saveNewNoteBtn');
  const addNotesToCartBtn = document.getElementById('addNotesToCartBtn');
  const noteSearchInput = document.getElementById('noteSearchInput'); // عنصر البحث الجديد

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
        <td><input type="number" class="form-control form-control-md qty-input" data-idx="${idx}" min="1" value="${item.quantity}"></td>
        <td><input type="number" class="form-control form-control-sm price-input" data-idx="${idx}" step="0.01" value="${item.unit_price}" data-auth="input_edit_price" ${canEditPrice ? '' : 'disabled' }></td>
        <td><span class="item-total">${(item.quantity * item.unit_price)}</span></td>
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

    document.querySelector('#cartTable').addEventListener('click', e => {
        if (e.target.classList.contains('view-notes-btn')) {
            const idx = parseInt(e.target.dataset.idx);
            const itemInCart = cart[idx];
            if (itemInCart && itemInCart.notes && itemInCart.notes.length > 0) {
                let notesContent = '<ul class="list-group">';
                itemInCart.notes.forEach(note => {
                    notesContent += `<li class="list-group-item">${note.text_ar} ${note.text_en ? '(' + note.text_en + ')' : ''}</li>`;
                });
                notesContent += '</ul>';
                alert(`ملاحظات لـ ${itemInCart.name}:\n\n${notesContent}`);
            } else {
                alert('لا توجد ملاحظات لهذا الصنف.');
            }
        }
    });

  // فتح نافذة الملاحظات (زر "إضافة مع ملاحظات")
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.add-with-notes');
        if (!btn) return;

        // Reset inputs and selected notes
        newNoteArInput.value = '';
        newNoteEnInput.value = '';
        currentSelectedNotes = [];
        updateSelectedNotesDisplay();
        availableNotesList.innerHTML = '<div class="text-center text-muted">جاري تحميل الملاحظات...</div>';

        const id = btn.dataset.id;
        const name = btn.dataset.name;
        const name_en = btn.dataset.namen;
        const price = parseFloat(btn.dataset.price);

        // *** التعديل هنا: البحث عن qtyInput داخل الـ .card الأب ***
        const itemCard = btn.closest('.card'); // ابحث عن الكارد الأب
        const qtyInput = itemCard ? itemCard.querySelector('.qty-input') : null; // ابحث عن qty-input داخل الكارد

        const quantity = parseFloat(qtyInput ? qtyInput.value : 1) || 1; // تأكد من وجود qtyInput قبل قراءة قيمته
        // ******************************************************

        const groupId = btn.dataset.groupId; // جلب الـ group_id من زر الصنف

        // Set modal data
        modalItemName.textContent = name;
        modalItemId.value = id;
        modalItemNameAr.value = name;
        modalItemNameEn.value = name_en;
        modalItemPrice.value = price;
        modalItemQty.value = quantity; // تم تحديث الكمية هنا
        modalItemGroupId.value = groupId;

        // Fetch notes for the current group
        await fetchAndDisplayNotes(groupId);

        notesModal.show();
    });

    const notesModalElement = document.getElementById('notesModal');

    // إضافة مستمع حدث لزر الإغلاق X
    notesModalElement.querySelector('.btn-close').addEventListener('click', () => {
        notesModal.hide();
    });

    // إضافة مستمع حدث لزر "إلغاء"
    document.getElementById('notesModal').querySelector('.btn-secondary[data-bs-dismiss="modal"]').addEventListener('click', () => {
        notesModal.hide();
    });

    // دالة لجلب وعرض الملاحظات المتاحة للمجموعة
    // دالة لجلب وعرض الملاحظات المتاحة للمجموعة
    async function fetchAndDisplayNotes(groupId) {
        try {
            const response = await fetch(`get_notes.php?group_id=${groupId}`);
            const result = await response.json();

            if (result.success) {
                availableNotesList.innerHTML = ''; // مسح الملاحظات السابقة
                const notesToDisplay = result.notes || [];

                if (notesToDisplay.length > 0) {
                    notesToDisplay.forEach(note => {
                        const isSelected = currentSelectedNotes.some(selectedNote => selectedNote.id == note.id);
                        
                        const colDiv = document.createElement('div');
                        colDiv.className = 'col'; // لتنسيق الشبكة
                        
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `btn btn-sm btn-outline-secondary note-card-btn ${isSelected ? 'active btn-primary text-white' : ''}`; // `active` و `btn-primary` للاختيار
                        button.dataset.noteId = note.id;
                        button.dataset.noteAr = note.text_ar;
                        button.dataset.noteEn = note.text_en || '';
                        button.textContent = note.text_ar; // عرض النص العربي على البطاقة
                        button.setAttribute('title', note.text_ar + (note.text_en ? ` (${note.text_en})` : '')); // تلميح عند المرور بالماوس

                        colDiv.appendChild(button);
                        availableNotesList.appendChild(colDiv);
                    });
                } else {
                    availableNotesList.innerHTML = '<div class="col w-100 text-center alert alert-info mt-3">لا توجد ملاحظات متاحة لهذه المجموعة بعد.</div>';
                }
            } else {
                availableNotesList.innerHTML = `<div class="col w-100 text-center alert alert-danger mt-3">فشل في تحميل الملاحظات: ${result.error}</div>`;
                console.error('Error fetching notes:', result.error);
            }
        } catch (error) {
            availableNotesList.innerHTML = `<div class="col w-100 text-center alert alert-danger mt-3">خطأ في الشبكة: ${error.message}</div>`;
            console.error('Network error fetching notes:', error);
        }
    }

    function areNotesEqual(notes1, notes2) {
        if (!notes1 && !notes2) return true; // كلاهما فارغ أو غير موجود
        if (!notes1 || !notes2) return false; // أحدهما فارغ والآخر لا
        if (notes1.length !== notes2.length) return false;

        // قم بفرز الملاحظات لضمان الترتيب المتناسق قبل المقارنة
        const sortedNotes1 = [...notes1].sort((a, b) => a.id - b.id);
        const sortedNotes2 = [...notes2].sort((a, b) => a.id - b.id);

        for (let i = 0; i < sortedNotes1.length; i++) {
            if (sortedNotes1[i].id !== sortedNotes2[i].id) return false;
            // يمكن إضافة مقارنات إضافية للنص إذا كان الـ ID غير كافٍ للفرادة
            // if (sortedNotes1[i].text_ar !== sortedNotes2[i].text_ar) return false;
        }
        return true;
    }

    // تحديث عرض الملاحظات المختارة في الـ Modal
    function updateSelectedNotesDisplay() {
        selectedNotesDisplay.innerHTML = '';
        if (currentSelectedNotes.length > 0) {
            const ul = document.createElement('ul');
            ul.className = 'list-unstyled mb-0'; // إزالة الهامش السفلي
            currentSelectedNotes.forEach(note => {
                const li = document.createElement('li');
                li.className = 'd-flex justify-content-between align-items-center mb-1'; // ترتيب العناصر
                li.innerHTML = `
                    <span>&bull; ${note.text_ar} ${note.text_en ? `(${note.text_en})` : ''}</span>
                    <button class="btn btn-sm btn-outline-danger remove-selected-note-btn" data-note-id="${note.id}" title="إزالة الملاحظة">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                `;
                ul.appendChild(li);
            });
            selectedNotesDisplay.appendChild(ul);
        } else {
            selectedNotesDisplay.innerHTML = '<p class="text-muted text-center mb-0">لا توجد ملاحظات مختارة بعد.</p>';
        }
    }

    // عند تغيير حالة مربع اختيار الملاحظة
    // عند النقر على بطاقة ملاحظة (للاختيار/الإلغاء)
    availableNotesList.addEventListener('click', (e) => {
        const button = e.target.closest('.note-card-btn'); // استخدام closest للعثور على الزر الأب
        if (!button) return; // تأكد أننا نقرنا على الزر

        const noteId = button.dataset.noteId;
        const noteAr = button.dataset.noteAr;
        const noteEn = button.dataset.noteEn;

        // Toggle 'active' class and 'btn-primary' for visual feedback
        const isActive = button.classList.toggle('active'); // تبديل حالة النشاط
        button.classList.toggle('btn-primary', isActive);
        button.classList.toggle('text-white', isActive);
        button.classList.toggle('btn-outline-secondary', !isActive);


        if (isActive) {
            // إضافة الملاحظة إذا لم تكن موجودة
            if (!currentSelectedNotes.some(note => note.id === noteId)) {
                currentSelectedNotes.push({ id: noteId, text_ar: noteAr, text_en: noteEn });
            }
        } else {
            // إزالة الملاحظة إذا تم إلغاء تحديدها
            currentSelectedNotes = currentSelectedNotes.filter(note => note.id !== noteId);
        }
        updateSelectedNotesDisplay();
    });

    // إزالة ملاحظة من القائمة المختارة يدويًا
    selectedNotesDisplay.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.remove-selected-note-btn'); // استخدام closest للعثور على الزر الأب
        if (!removeBtn) return;

        const noteIdToRemove = removeBtn.dataset.noteId;
        currentSelectedNotes = currentSelectedNotes.filter(note => note.id !== noteIdToRemove);

        // إلغاء تحديد البطاقة المقابلة في قائمة الملاحظات المتاحة
        const correspondingButton = availableNotesList.querySelector(`.note-card-btn[data-note-id="${noteIdToRemove}"]`);
        if (correspondingButton) {
            correspondingButton.classList.remove('active', 'btn-primary', 'text-white');
            correspondingButton.classList.add('btn-outline-secondary');
        }
        updateSelectedNotesDisplay();
    });


    // حفظ ملاحظة جديدة
    saveNewNoteBtn.addEventListener('click', async () => {
        const noteTextAr = newNoteArInput.value.trim();
        const noteTextEn = newNoteEnInput.value.trim();
        const groupId = modalItemGroupId.value;

        if (!noteTextAr) {
            alert('الرجاء إدخال نص الملاحظة بالعربي.');
            return;
        }
        if (!groupId) {
            alert('لم يتم تحديد مجموعة للصنف.');
            return;
        }

        try {
            const response = await fetch('save_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_text_ar: noteTextAr, note_text_en: noteTextEn, group_id: groupId }),
            });
            const result = await response.json();

            if (result.success) {
                alert('تم حفظ الملاحظة بنجاح!');
                newNoteArInput.value = '';
                newNoteEnInput.value = '';
                // أعد تحميل الملاحظات لتضمين الجديدة
                await fetchAndDisplayNotes(groupId);
                // قم بتحديد الملاحظة الجديدة تلقائياً إذا أردت
                // const newNoteCheckbox = availableNotesList.querySelector(`.note-checkbox[data-note-id="${result.note_id}"]`);
                // if (newNoteCheckbox) {
                //     newNoteCheckbox.checked = true;
                //     currentSelectedNotes.push({ id: result.note_id, text_ar: result.text_ar, text_en: result.text_en });
                //     updateSelectedNotesDisplay();
                // }

            } else {
                alert('فشل في حفظ الملاحظة: ' + (result.error || 'خطأ غير معروف'));
            }
        } catch (error) {
            console.error('Error saving new note:', error);
            alert('خطأ في الاتصال بالخادم أثناء حفظ الملاحظة.');
        }
    });

    // إضافة الصنف مع الملاحظات إلى السلة
    addNotesToCartBtn.addEventListener('click', () => {
        const id = modalItemId.value;
        const name = modalItemNameAr.value;
        const name_en = modalItemNameEn.value;
        const price = parseFloat(modalItemPrice.value);
        const quantity = parseFloat(modalItemQty.value);
        const notes = currentSelectedNotes; // الملاحظات المختارة

        // ابحث عن صنف موجود بنفس الـ ID ونفس الملاحظات
        const existingIndex = cart.findIndex(item => 
            item.item_id === id && areNotesEqual(item.notes, notes)
        );

        if (existingIndex !== -1) {
            // إذا كان الصنف موجودًا بنفس الملاحظات، قم بزيادة الكمية
            cart[existingIndex].quantity += quantity;
        } else {
            // إذا لم يكن موجودًا (أو كان موجودًا بملاحظات مختلفة)، أضفه كصنف جديد
            cart.push({ item_id: id, name, name_en, unit_price: price, quantity, notes });
        }

        renderCart();
        notesModal.hide(); // إخفاء نافذة الملاحظات بعد الإضافة
    });

  function recalcTotals() {
    let subTotal = 0;
    document.querySelectorAll('#cartTable tbody tr').forEach(tr => {
      const qty = Math.max(1, parseFloat(tr.querySelector('.qty-input').value)||1);
      const priceField = tr.querySelector('.price-input');
      const price = Math.max(0, parseFloat(priceField.value)||0);
      const lineTot = qty * price;
      subTotal += lineTot;
      tr.querySelector('.item-total').textContent = lineTot;
    });
    
    const discount = Math.max(0, parseFloat(document.getElementById('discountInput').value) || 0);
    const taxAmount = subTotal * taxRate / 100;
    const grandTotal = subTotal + taxAmount - discount;
    
    document.getElementById('subTotal').textContent = subTotal;
    document.getElementById('taxAmount').textContent = taxAmount;
    document.getElementById('grandTotal').textContent = grandTotal;
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
    
    // **التعديل المقترح هنا:**
    const itemCard = btn.closest('.card'); // البحث عن العنصر الأب الذي يمثل البطاقة
    const qtyInput = itemCard ? itemCard.querySelector('.qty-input') : null;
    const quantity = Math.max(1, parseFloat(qtyInput ? qtyInput.value : 1) || 1);

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
    
    // ***** هنا التعديل *****
    for (const [gidStr, items] of Object.entries(itemsByGroup)) {
      const gid = parseInt(gidStr);
      const printerId = parseInt(groupPrinters[gid] || 0);
      
      // الشرط الجديد: لا تُنشئ فاتورة مفصلة إلا إذا كانت المجموعة مرتبطة بطابعة
      if (printerId) {
        const img = await generateInvoiceImage(items, subTotal, taxAmount, total, result.orderSeq, fsTitle, fsItem, fsTotal);
        images.push({ image: img, printer_ids: [printerId] });
      }
    }
    // ************************

    if (unassignedPrinters.length) {
      const fullImg = await generateInvoiceImage(cart, subTotal, taxAmount, total, result.orderSeq, fsTitle, fsItem, fsTotal);
      images.push({ image: fullImg, printer_ids: unassignedPrinters });
    }

    cart.length = 0;
    renderCart();

    const printResp = await fetch('../src/print.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ images, order_id: result.orderId }) });
    const printResult = await printResp.json();

    if (printResult.success) { 
      toastOk('تم إنشاء الطلب وطباعة الفاتورة'); 
//       cart.length = 0; 
//       renderCart(); 
    } else { 
      toastErr('تم البيع ولكن فشلت الطباعة'); 
      console.error(printResult);
      // Clear cart and show error message even when printing fails
      
    }
}

  async function generateInvoiceImage(items, subTotal, taxAmount, total, orderSeq, fsTitle, fsItem, fsTotal) {
    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
    const settings = await fetch('get_print_settings.php').then(r => r.json()); const cfg = {}; settings.forEach(s => cfg[s.key] = s);
    const widthMm = parseInt(cfg['print_width_mm']?.value) || 80; const pxPerMm = 7; const width = widthMm * pxPerMm;

    // Define the padding for the entire invoice
    const padding = 20;

    const infoLines = [];
    if (cfg['field_restaurant_name']?.value == 1) infoLines.push(cfg['restaurant_name']?.value || '');
    if (cfg['field_username']?.value == 1) infoLines.push('المستخدم: ' + '<?= htmlspecialchars($_SESSION["username"]) ?>');
    if (cfg['field_tax_number']?.value == 1) infoLines.push('الرقم الضريبي: ' + (cfg['tax_number']?.value || ''));
    infoLines.push(cfg['address']?.value || '');

    const rowH = 45, headerH = 100, infoH = infoLines.length * 25, footerH = 110, extra = 320;

    let tableRowsHeight = 0;
    items.forEach(item => {
        let itemActualRowHeight = rowH;
        const noteTexts = (item.notes || []).map(note => note.text_ar).filter(Boolean).join(' | ');
        const hasNotes = noteTexts.length > 0;
        if (hasNotes) {
            const tempCtx = canvas.getContext('2d');
            tempCtx.font = `${Math.max(12, fsItem)}px Arial`;
            const maxAllowedWidthForNotes = width - (2 * padding);
            const measuredWidth = tempCtx.measureText(noteTexts).width;
            itemActualRowHeight += Math.ceil(measuredWidth / maxAllowedWidthForNotes) * 18 + 5;
        }
        tableRowsHeight += itemActualRowHeight;
    });

    canvas.width = width;
    canvas.height = headerH + infoH + tableRowsHeight + footerH + extra;

    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, width, canvas.height);

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

    ctx.fillStyle = 'black';
    ctx.textAlign = 'center';
    ctx.font = `${Math.max(16, fsTitle)}px Arial`;
    infoLines.forEach(line => {
        ctx.fillText(line, width / 2, y);
        y += 25;
    });

    ctx.font = `bold ${Math.max(16, fsTitle)}px Arial`;
    ctx.textAlign = 'right';
    ctx.fillStyle = '#111';
    ctx.fillText(`رقم الطلب: ${orderSeq}`, width - padding, y + 40);
    y += 60;

    const tableWidth = width - (2 * padding);
    const cols = ['اسم', 'كمية', 'سعر إفرادي', 'سعر إجمالي'];
    const colW = [tableWidth * 0.4, tableWidth * 0.1, tableWidth * 0.26, tableWidth * 0.26];

    ctx.font = `bold ${Math.max(12, fsItem)}px Arial`;
    ctx.textAlign = 'center';
    let x = width - padding;
    cols.forEach((title, i) => {
        x -= colW[i];
        ctx.strokeStyle = '#ccc';
        ctx.strokeRect(x, y, colW[i], rowH);
        ctx.fillText(title, x + colW[i] / 2, y + rowH / 2);
    });
    y += rowH;

    ctx.font = `${Math.max(12, fsItem)}px Arial`;
    ctx.fillStyle = '#000';
    items.forEach(item => {
        let x = width - padding;

        const nameLines = [item.name];
        if (item.name_en) nameLines.push(item.name_en);

        const noteTexts = (item.notes || []).map(note => note.text_ar).filter(Boolean).join(' | ');
        const hasNotes = noteTexts.length > 0;
        let itemActualRowHeight = rowH;
        if (hasNotes) {
            const tempCtx = canvas.getContext('2d');
            tempCtx.font = `${Math.max(12, fsItem)}px Arial`;
            const maxAllowedWidthForNotes = width - (2 * padding);
            const measuredWidth = tempCtx.measureText(noteTexts).width;
            itemActualRowHeight += Math.ceil(measuredWidth / maxAllowedWidthForNotes) * 18 + 5;
        }

        const qty = item.quantity;
        const price = item.unit_price;
        const tot = (qty * item.unit_price);
        const cells = [nameLines.join('\n'), qty, price, tot];

        cells.forEach((txt, i) => {
            x -= colW[i];
            ctx.strokeStyle = '#eee';
            ctx.strokeRect(x, y, colW[i], itemActualRowHeight);

            // Set alignment based on column
            ctx.textAlign = 'center';
            if (i === 0) {
                ctx.textAlign = 'right';
                ctx.fillText(txt, x + colW[i] - 5, y + itemActualRowHeight / 2);
            } else if (i === 1) {
                ctx.textAlign = 'center';
                ctx.fillText(txt, x + colW[i] / 2, y + itemActualRowHeight / 2);
            } else {
                ctx.textAlign = 'left';
                ctx.fillText(txt, x + 10, y + itemActualRowHeight / 2);
            }
        });

        if (hasNotes) {
            ctx.font = `${Math.max(12, fsItem)}px Arial`;
            ctx.fillStyle = '#444';
            ctx.textAlign = 'right';
            const maxTextWidth = width - (2 * padding);
            const words = noteTexts.split(' ');
            let currentPrintLine = '';
            let tempNoteY = y + rowH;
            for (let j = 0; j < words.length; j++) {
                let testLine = currentPrintLine + words[j] + ' ';
                if (ctx.measureText(testLine).width > maxTextWidth && j > 0) {
                    ctx.fillText(currentPrintLine.trim(), width - padding, tempNoteY + 18);
                    tempNoteY += 18;
                    currentPrintLine = words[j] + ' ';
                } else {
                    currentPrintLine = testLine;
                }
            }
            ctx.fillText(currentPrintLine.trim(), width - padding, tempNoteY + 18);
            ctx.fillStyle = '#000';
            ctx.textAlign = 'center';
        }

        y += itemActualRowHeight;
    });

    y += 20;
    ctx.beginPath();
    ctx.moveTo(padding, y);
    ctx.lineTo(width - padding, y);
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 1;
    ctx.stroke();
    y += 30;

    ctx.font = `bold ${Math.max(12, fsTotal)}px Arial`;
    ctx.textAlign = 'right';
    ctx.fillStyle = '#111';
    ctx.fillText(`المجموع: ${subTotal}`, width - padding, y + 20);
    ctx.fillText(`الضريبة: ${taxAmount}`, width - padding, y + 45);
    ctx.fillText(`الإجمالي: ${total}`, width - padding, y + 70);

    const farewellMessage = 'شكراً لزيارتكم';
    const now = new Date();
    const dateTimeString = now.toLocaleString('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).replace(',', '');

    ctx.font = `${Math.max(12, fsItem)}px Arial`;
    ctx.textAlign = 'center';
    ctx.fillText(farewellMessage, width / 2, y + 110);
    ctx.textAlign = 'center';
    ctx.fillText(dateTimeString, width / 2, y + 140);

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
                <div class="row mt-2">
                <button ${item.stock <= 0 ? 'disabled' : ''} class="btn btn-${item.stock <= 0 ? 'danger' : 'success'} w-100 add-to-cart"
                        data-id="${item.id}"
                        data-name="${item.name_ar}"
                        data-namen="${item.name_en}"
                        data-price="${item.price}"
                        data-group-id="${item.group_id}">
                  ${item.stock <= 0 ? 'غير متاح' : 'إضافة للسلة'}
                </button>
                </div>
                <div class="row mt-2">
                    <button ${item.stock == 0 ? 'disabled' : ''} class="btn btn-outline-info add-with-notes w-100 pt-2"
                        data-id="${item.id}"
                        data-name="${item.name_ar}"
                        data-namen="${item.name_en}"
                        data-price="${item.price}"
                        data-group-id="${groupId}"> ملاحظات
                    </button>
                </div>
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
// public/js/app.js
document.addEventListener('DOMContentLoaded', () => {
  let currentGroup = 0;
  const groupList = document.getElementById('groupList');
  const itemsGrid = document.getElementById('itemsGrid');
//   const addItemBtn = document.getElementById('addItemBtn');
  const itemModalEl = document.getElementById('itemModal');
  const itemForm = document.getElementById('addItemForm');
//   const modal = new bootstrap.Modal(itemModalEl);

  // تحميل الأصناف في هيئة بطاقات
  function loadItems(groupId) {
    itemsGrid.innerHTML = '<div class="text-center p-5">جاري التحميل...</div>';
    fetch(`get_items.php?group_id=${groupId}&layout=grid`)
      .then(res => res.json())
      .then(items => {
        itemsGrid.innerHTML = items.map(it => `
          <div class="col">
            <div class="card h-100">
              <div class="card-body">
                <h5 class="card-title">${it.name_ar}</h5>
                <p class="card-text">وحدة: ${it.unit}<br>السعر: ${it.price}</p>
              </div>
              <div class="card-footer d-flex justify-content-between">
                <button class="btn btn-sm btn-warning" onclick="editItem(${it.id}, ${it.group_id})">تعديل</button>
                <button class="btn btn-sm btn-danger" onclick="deleteItem(${it.id}, ${it.group_id})">حذف</button>
              </div>
            </div>
          </div>
        `).join('');
      })
      .catch(() => { itemsGrid.innerHTML = '<div class="text-danger">فشل في التحميل</div>'; });
  }

  // اختيار مجموعة
  groupList.querySelectorAll('.list-group-item').forEach(btn => {
    btn.addEventListener('click', () => {
      groupList.querySelectorAll('.list-group-item').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentGroup = btn.getAttribute('data-group-id');
      loadItems(currentGroup);
    });
  });

//   // فتح المودال للإضافة
//   addItemBtn.addEventListener('click', () => {
//     itemForm.reset();
//     itemForm.id.value = '';
//     itemForm.group_id.value = currentGroup;
//     // modal.show();
//   });

  // حفظ البيانات عبر AJAX
  itemForm.addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('item_save.php', {
      method: 'POST',
      body: new FormData(itemForm)
    })
    .then(res => res.json())
    .then(resp => {
        console.log(resp);
      if (resp.success) {
        modal.hide();
        loadItems(currentGroup);
      } else {
        alert('خطأ: ' + resp.error);
      }
    })
    .catch(() => alert('فشل في حفظ الصنف'));
  });

  // تعاريف عالمية للـ edit/delete
  window.editItem = (id, groupId) => {
    // جلب بيانات صنف واحد
    fetch(`get_items.php?group_id=${groupId}&single_id=${id}`)
      .then(res => res.json())
      .then(data => {
        itemForm.id.value = data.id;
        itemForm.name_ar.value = data.name_ar;
        itemForm.name_en.value = data.name_en;
        itemForm.barcode.value = data.barcode;
        itemForm.price.value = data.price;
        itemForm.stock.value = data.stock;
        itemForm.unit.value = data.unit;
        itemForm.group_id.value = data.group_id;
        // modal.show();
      });
  };
  window.deleteItem = (id, groupId) => {
    if (!confirm('هل تريد حذف هذا الصنف؟')) return;
    fetch(`item_delete.php?id=${id}`, { method: 'POST' })
      .then(() => loadItems(groupId));
  };

  // التحميل الأولي
  loadItems(currentGroup);
});



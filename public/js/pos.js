const groups = [];
  const itemsByGroup = {}; // { groupId: [items] }
  let taxRate = 0;
  let cart = [];

  // استدعاء البيانات
  window.onload = () => {
    fetch('../src/Order.php')
      .then(res => res.json())
      .then(data => {
        taxRate = data.tax;
        document.getElementById('taxRate').innerText = taxRate;
        renderGroups(data.groups);
        itemsByGroup = data.items;
      });
  };

  function renderGroups(groupList) {
    const groupsContainer = document.getElementById('groups');
    groupList.forEach(g => {
      const btn = document.createElement('button');
      btn.className = 'btn btn-outline-primary group-button';
      btn.innerText = g.name;
      btn.onclick = () => renderItems(g.id);
      groupsContainer.appendChild(btn);
    });
  }

  function renderItems(groupId) {
    const items = itemsByGroup[groupId] || [];
    const container = document.getElementById('itemsContainer');
    container.innerHTML = '';

    items.forEach(item => {
      const col = document.createElement('div');
      col.className = 'col-md-4 mb-3';

      const card = document.createElement('div');
      card.className = 'card item-card';
      card.innerHTML = `
        <img src="/public/images/items/${item.image}" class="card-img-top" alt="...">
        <div class="card-body">
          <h6 class="card-title">${item.name_ar}</h6>
          <p class="card-text">السعر: ${item.price} ل.س</p>
          <input type="number" min="1" value="1" class="form-control mb-2 quantity" data-id="${item.id}">
          <button class="btn btn-sm btn-primary w-100 addToCart" data-id="${item.id}">أضف للسلة</button>
        </div>
      `;
      col.appendChild(card);
      container.appendChild(col);
    });

    setTimeout(() => {
      document.querySelectorAll('.addToCart').forEach(btn => {
        btn.onclick = () => {
          const id = btn.dataset.id;
          const qtyInput = btn.parentElement.querySelector('.quantity');
          const quantity = parseInt(qtyInput.value);
          const item = items.find(i => i.id == id);
          addToCart(item, quantity);
        }
      });
    }, 100);
  }

  function addToCart(item, quantity) {
    const existing = cart.find(c => c.id === item.id);
    if (existing) existing.quantity += quantity;
    else cart.push({ ...item, quantity });
    updateCartDisplay();
  }

  function updateCartDisplay() {
    const list = document.getElementById('cartList');
    list.innerHTML = '';
    let total = 0;
    cart.forEach(c => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center';
      li.innerHTML = `${c.name_ar} × ${c.quantity} <span>${c.price * c.quantity}</span>`;
      list.appendChild(li);
      total += c.price * c.quantity;
    });

    const tax = Math.round(total * (taxRate / 100));
    const final = total + tax;

    document.getElementById('totalAmount').innerText = total;
    document.getElementById('taxAmount').innerText = tax;
    document.getElementById('finalTotal').innerText = final;
  }

  document.getElementById('submitOrder').onclick = () => {
    if (cart.length === 0) return alert('السلة فارغة');
    fetch('/src/api/submit_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cart, taxRate })
    }).then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('تم إنشاء الطلب بنجاح');
          cart = [];
          updateCartDisplay();
        } else {
          alert('حدث خطأ أثناء إنشاء الطلب');
        }
      });
  };
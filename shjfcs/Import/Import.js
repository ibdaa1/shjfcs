// Import.js - handles UI and AJAX for Import.html
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('inspectionForm');
  const inspectionsTable = document.querySelector('#inspectionsTable tbody');
  const productsTableBody = document.querySelector('#productsTable tbody');
  const addProductBtn = document.getElementById('addProductBtn');
  const resetBtn = document.getElementById('resetBtn');

  // selects
  const portsSelect = document.getElementById('entry_port_id');
  const inspectorsSelect = document.getElementById('inspector_emp_id');
  const actionsSelect = document.getElementById('action_taken_id');
  const countriesSelect = document.getElementById('p_country_id');
  const categoriesSelect = document.getElementById('p_category_id');

  let editingProductIndex = -1;
  let products = [];

  function fetchJSON(url) {
    return fetch(url).then(r => r.json());
  }

  function loadLookups() {
    fetchJSON('get_ports.php').then(r => {
      if (Array.isArray(r)) {
        r.forEach(p => portsSelect.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.port_name}</option>`));
      }
    });
    fetchJSON('get_user_data.php').then(r => {
      if (Array.isArray(r.data)) {
        r.data.forEach(u => inspectorsSelect.insertAdjacentHTML('beforeend', `<option value="${u.EmpID}">${u.EmpName}</option>`));
      }
    });
    fetchJSON('get_action_types.php').then(r => {
      if (Array.isArray(r)) {
        r.forEach(a => actionsSelect.insertAdjacentHTML('beforeend', `<option value="${a.id}">${a.action_name}</option>`));
      }
    });
    fetchJSON('get_countries.php').then(r => {
      if (Array.isArray(r.data)) {
        r.data.forEach(c => countriesSelect.insertAdjacentHTML('beforeend', `<option value="${c.ID}">${c.ARABIC_NAME}</option>`));
      }
    });
    fetchJSON('get_product_categories.php').then(r => {
      if (Array.isArray(r)) {
        r.forEach(c => categoriesSelect.insertAdjacentHTML('beforeend', `<option value="${c.CATEGORY_ID}">${c.CATEGORY_NAME_AR}</option>`));
      }
    });
  }

  function renderProducts() {
    productsTableBody.innerHTML = '';
    products.forEach((p, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(p.product_name)}</td>
        <td>${escapeHtml(p.category_name || '')}</td>
        <td>${p.weight || ''}</td>
        <td>${p.quantity || ''}</td>
        <td>${escapeHtml(p.country_name || '')}</td>
        <td>${p.production_date || ''}</td>
        <td>${p.expiry_date || ''}</td>
        <td>${escapeHtml(p.notes || '')}</td>
        <td>
          <button data-idx="${idx}" class="edit-product">تعديل</button>
          <button data-idx="${idx}" class="delete-product">حذف</button>
        </td>`;
      productsTableBody.appendChild(tr);
    });
  }

  function escapeHtml(s){ return (s+'').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

  addProductBtn.addEventListener('click', function () {
    const product = {
      product_name: document.getElementById('p_product_name').value.trim(),
      brand_name: document.getElementById('p_brand_name').value.trim(),
      weight: parseFloat(document.getElementById('p_weight').value) || 0,
      quantity: parseFloat(document.getElementById('p_quantity').value) || 0,
      country_id: document.getElementById('p_country_id').value || null,
      country_name: document.getElementById('p_country_id').selectedOptions[0]?.text || '',
      category_id: document.getElementById('p_category_id').value || null,
      category_name: document.getElementById('p_category_id').selectedOptions[0]?.text || '',
      production_date: document.getElementById('p_production_date').value || '',
      expiry_date: document.getElementById('p_expiry_date').value || '',
      notes: document.getElementById('p_notes').value || ''
    };
    if (!product.product_name) {
      alert('يرجى إدخال اسم المنتج');
      return;
    }
    if (editingProductIndex >= 0) {
      products[editingProductIndex] = product;
      editingProductIndex = -1;
      addProductBtn.textContent = 'إضافة منتج';
    } else {
      products.push(product);
    }
    clearProductInputs();
    renderProducts();
  });

  function clearProductInputs() {
    ['p_product_name','p_brand_name','p_weight','p_quantity','p_country_id','p_category_id','p_production_date','p_expiry_date','p_notes'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
      }
    });
  }

  productsTableBody.addEventListener('click', function(e){
    if (e.target.matches('.delete-product')) {
      const idx = parseInt(e.target.dataset.idx,10);
      if (confirm('هل أنت متأكد من حذف المنتج؟')) {
        products.splice(idx,1);
        renderProducts();
      }
    } else if (e.target.matches('.edit-product')) {
      const idx = parseInt(e.target.dataset.idx,10);
      const p = products[idx];
      document.getElementById('p_product_name').value = p.product_name;
      document.getElementById('p_brand_name').value = p.brand_name;
      document.getElementById('p_weight').value = p.weight;
      document.getElementById('p_quantity').value = p.quantity;
      document.getElementById('p_country_id').value = p.country_id || '';
      document.getElementById('p_category_id').value = p.category_id || '';
      document.getElementById('p_production_date').value = p.production_date || '';
      document.getElementById('p_expiry_date').value = p.expiry_date || '';
      document.getElementById('p_notes').value = p.notes || '';
      editingProductIndex = idx;
      addProductBtn.textContent = 'حفظ التعديل';
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const payload = {
      ID: document.getElementById('ID').value || null,
      unique_id: document.getElementById('unique_id').value.trim(),
      registration_date: document.getElementById('registration_date').value || null,
      entry_port_id: document.getElementById('entry_port_id').value || null,
      system_registration_no: document.getElementById('system_registration_no').value || null,
      container_count: document.getElementById('container_count').value || 0,
      container_numbers: document.getElementById('container_numbers').value || null,
      actual_inspection_date: document.getElementById('actual_inspection_date').value || null,
      inspector_emp_id: document.getElementById('inspector_emp_id').value || null,
      action_taken_id: document.getElementById('action_taken_id').value || null,
      notes: document.getElementById('notes').value || null,
      products: products
    };
    const isUpdate = Boolean(payload.ID);
    const url = 'Import.php?action=' + (isUpdate ? 'update' : 'create');
    fetch(url, {
      method: 'POST',
      headers: {'Content-Type':'application/json; charset=utf-8'},
      body: JSON.stringify(payload)
    }).then(r => r.json()).then(result => {
      if (result && result.success) {
        alert('تم الحفظ بنجاح');
        resetForm();
        loadInspections();
      } else {
        alert('حصل خطأ: ' + (result.error || JSON.stringify(result)));
      }
    }).catch(err => {
      alert('Network error: ' + err);
    });
  });

  resetBtn.addEventListener('click', resetForm);

  function resetForm() {
    form.reset();
    products = [];
    renderProducts();
    document.getElementById('ID').value = '';
    editingProductIndex = -1;
    addProductBtn.textContent = 'إضافة منتج';
  }

  function loadInspections() {
    fetchJSON('Import.php?action=list').then(r => {
      inspectionsTable.innerHTML = '';
      (r.data || []).forEach((row, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${idx+1}</td>
          <td>${escapeHtml(row.unique_id||'')}</td>
          <td>${row.registration_date||''}</td>
          <td>${escapeHtml(row.port_name||'')}</td>
          <td>${escapeHtml(row.inspector_name||'')}</td>
          <td>${escapeHtml(row.action_name||'')}</td>
          <td>${escapeHtml(row.notes||'')}</td>
          <td>
            <button data-id="${row.ID}" class="edit-inspection">تعديل</button>
            <button data-id="${row.ID}" class="delete-inspection">حذف</button>
          </td>`;
        inspectionsTable.appendChild(tr);
      });
    });
  }

  inspectionsTable.addEventListener('click', function (e) {
    if (e.target.matches('.edit-inspection')) {
      const id = e.target.dataset.id;
      fetchJSON('Import.php?action=get&id=' + id).then(r => {
        const d = r.data;
        if (!d) { alert('سجل غير موجود'); return; }
        document.getElementById('ID').value = d.ID;
        document.getElementById('unique_id').value = d.unique_id || '';
        document.getElementById('registration_date').value = d.registration_date ? d.registration_date.replace(' ', 'T') : '';
        document.getElementById('entry_port_id').value = d.entry_port_id || '';
        document.getElementById('system_registration_no').value = d.system_registration_no || '';
        document.getElementById('container_count').value = d.container_count || 0;
        document.getElementById('container_numbers').value = d.container_numbers || '';
        document.getElementById('actual_inspection_date').value = d.actual_inspection_date ? d.actual_inspection_date.replace(' ', 'T') : '';
        document.getElementById('inspector_emp_id').value = d.inspector_emp_id || '';
        document.getElementById('action_taken_id').value = d.action_taken_id || '';
        document.getElementById('notes').value = d.notes || '';
        products = d.products || [];
        renderProducts();
        window.scrollTo({top:0, behavior:'smooth'});
      });
    } else if (e.target.matches('.delete-inspection')) {
      const id = e.target.dataset.id;
      if (!confirm('هل تريد حذف هذا الفحص؟')) return;
      fetch('Import.php?action=delete&id=' + id).then(r => r.json()).then(res => {
        if (res.success) {
          alert('تم الحذف');
          loadInspections();
          resetForm();
        } else alert('خطأ: ' + (res.error || JSON.stringify(res)));
      });
    }
  });

  // init
  loadLookups();
  loadInspections();
});

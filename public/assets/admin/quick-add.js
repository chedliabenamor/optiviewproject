(function(){
  function ensureModal(){
    if (document.getElementById('ea-quick-add-modal')) return;
    var wrapper = document.createElement('div');
    wrapper.innerHTML = `
<div class="modal fade" id="ea-quick-add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ea-quick-add-title">Quick Add</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="ea-quick-add-body">
        <div class="text-center text-muted py-4">
          <i class="fa fa-spinner fa-spin me-2"></i> Loading form...
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="ea-quick-add-save">Save</button>
      </div>
    </div>
  </div>
</div>`;
    var el = wrapper.firstElementChild;
    if (el) document.body.appendChild(el);
  }

  var originSelect = null;
  var currentFetchUrl = null;
  var modal, modalEl, bodyEl, titleEl, saveBtn;

  function initRefs(){
    ensureModal();
    modalEl = document.getElementById('ea-quick-add-modal');
    if (!modalEl) return false;
    modal = (window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    bodyEl = document.getElementById('ea-quick-add-body');
    titleEl = document.getElementById('ea-quick-add-title');
    saveBtn = document.getElementById('ea-quick-add-save');
    return true;
  }

  // Open modal and load form
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.ea-quick-add');
    if (!btn) return;
    if (!initRefs()) return;

    var wrapper = btn.closest('.form-group');
    originSelect = wrapper ? (wrapper.querySelector('select, input[data-ea-autocomplete]')) : null;
    var fetchUrl = btn.getAttribute('data-fetch');
    var title = btn.getAttribute('data-title') || 'Quick Add';

    if (titleEl) titleEl.textContent = title;
    if (bodyEl) bodyEl.innerHTML = '<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin me-2"></i> Loading form...</div>';
    currentFetchUrl = fetchUrl;

    fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.text(); })
      .then(function(html){ if (bodyEl) bodyEl.innerHTML = html; if (modal) modal.show(); })
      .catch(function(){ if (bodyEl) bodyEl.innerHTML = '<div class="text-danger">Failed to load form. Please retry.</div>'; if (modal) modal.show(); });
  });

  // Save
  document.addEventListener('click', function(e){
    if (!saveBtn || e.target !== saveBtn) return;
    if (!bodyEl) return;
    var form = bodyEl.querySelector('form');
    if (!form) return;

    saveBtn.disabled = true;
    var originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';

    var fd = new FormData(form);
    var postUrl = form.getAttribute('action') || currentFetchUrl;
    fetch(postUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
      .then(function(r){
        return r.clone().json().catch(function(){ return r.text(); });
      })
      .then(function(data){
        if (data && typeof data === 'object' && data.success && data.entity && data.entity.id && originSelect) {
          var id = String(data.entity.id);
          var label = data.entity.name || ('#' + id);
          if (originSelect.tomselect) {
            var ts = originSelect.tomselect;
            ts.addOption({ value: id, text: label });
            ts.refreshOptions(false);
            ts.addItem(id);
          } else if (originSelect.tagName === 'SELECT') {
            var opt = document.createElement('option');
            opt.value = id;
            opt.textContent = label;
            opt.selected = true;
            originSelect.appendChild(opt);
            originSelect.value = label;
          }
          if (modal) modal.hide();
        } else if (data && typeof data === 'object' && data.form_html) {
          bodyEl.innerHTML = data.form_html;
        } else if (typeof data === 'string') {
          bodyEl.innerHTML = data;
        }
      })
      .catch(function(){ if (bodyEl) bodyEl.innerHTML = '<div class="text-danger">Failed to save. Please try again.</div>'; })
      .finally(function(){ saveBtn.disabled = false; saveBtn.textContent = originalText; });
  });
})();

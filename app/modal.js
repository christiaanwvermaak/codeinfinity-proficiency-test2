// Centralized modal behavior for the reusable modal partial
// Moves the modal element to document.body and applies inline fallback positioning
(function(){
  function initModal() {
    var modal = document.getElementById('uploadModal');
    if (!modal) return;
    try {
      // Inline fallback styles (will be overridden by CSS)
      modal.style.position = 'fixed';
      modal.style.top = '0';
      modal.style.left = '0';
      modal.style.right = '0';
      modal.style.bottom = '0';
      modal.style.zIndex = '99999';
      modal.style.pointerEvents = 'none';
      // Move to body so fixed positioning covers the viewport
      if (modal.parentNode !== document.body) {
        document.body.appendChild(modal);
      }
    } catch (e) {
      console.warn('modal positioning fallback failed', e);
    }
    // central event wiring for modal buttons and keyboard
    var closeBtn = modal.querySelector('#modalClose');
    var cancelBtn = modal.querySelector('#modalCancel');

    function closeModal(){
      modal.classList.remove('open');
      document.body.classList.remove('modal-open');
      modal.setAttribute('aria-hidden', 'true');
      // make sure cancel button is hidden
      if (cancelBtn) cancelBtn.style.display = 'none';
      // remove focus trap when closing
      document.removeEventListener('focus', trapFocus, true);
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', closeModal);
    }

    // confirm-on-cancel: show a small confirmation overlay before aborting
    var confirmOverlay = null;
    function ensureConfirmOverlay(){
      if (confirmOverlay) return confirmOverlay;
      // read customization from modal data attributes if present
      var confirmText = modal.dataset.confirmText || 'Cancel the upload? This will stop the current upload in progress.';
      var confirmYes = modal.dataset.confirmYes || 'Yes, cancel';
      var confirmNo = modal.dataset.confirmNo || 'No, keep uploading';
      confirmOverlay = document.createElement('div');
      confirmOverlay.className = 'confirm-overlay';
      confirmOverlay.innerHTML = '<div class="confirm-box"><div>' + confirmText + '</div><div class="confirm-actions"><button id="confirmYes" class="btn primary">' + confirmYes + '</button><button id="confirmNo" class="btn ghost">' + confirmNo + '</button></div></div>';
      document.body.appendChild(confirmOverlay);
      return confirmOverlay;
    }

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function(){
        var overlay = ensureConfirmOverlay();
        overlay.classList.add('open');
        // wire buttons
        var yes = overlay.querySelector('#confirmYes');
        var no = overlay.querySelector('#confirmNo');
        function hideOverlay(){ overlay.classList.remove('open'); }
        yes.onclick = function(){
          hideOverlay();
          try { if (window.__activeUploadXhr && typeof window.__activeUploadXhr.abort === 'function') { window.__activeUploadXhr.abort(); window.__activeUploadXhr = null; } } catch(e){ console.warn('Failed to abort upload', e); }
          closeModal();
        };
        no.onclick = function(){ hideOverlay(); };
      });
    }

    // close on escape
    document.addEventListener('keydown', function escHandler(e){ if (e.key === 'Escape') { closeModal(); } });

    // Expose a small API to control modal from other scripts
    window.modal = window.modal || {};
    window.modal.close = closeModal;
    window.modal.showCancel = function(yes){ if (cancelBtn) cancelBtn.style.display = yes ? 'inline-block' : 'none'; };
    window.modal.setProgress = function(pct){
      var progress = modal.querySelector('#uploadProgress');
      var pctEl = modal.querySelector('#modalPct');
      if (progress) { progress.value = pct; }
      if (pctEl) { pctEl.textContent = pct ? pct + '%' : ''; }
    };
    window.modal.setMessage = function(msg){ var txt = modal.querySelector('#loadingText'); if (txt) txt.innerHTML = msg; };

    // Focus trap: keep focus within modal while open
    function trapFocus(e){
      if (!modal.classList.contains('open')) return;
      if (!modal.contains(e.target)) {
        e.stopPropagation();
        // move focus to first focusable inside modal
        var focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable) focusable.focus();
      }
    }
    document.addEventListener('focus', trapFocus, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModal);
  } else {
    initModal();
  }
})();

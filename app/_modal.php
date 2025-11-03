<?php
// Reusable upload status modal partial
// Usage: include __DIR__ . '/_modal.php';
?>
<div id="uploadModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" style="position:fixed;top:0;left:0;right:0;bottom:0;display:none;pointer-events:none;z-index:99999;">
  <div class="modal-backdrop"></div>
  <div class="modal-content" role="document" aria-labelledby="modalTitle" aria-describedby="modalDesc">
  <button id="modalClose" class="modal-close" aria-label="Close">✕</button>
  <button id="modalCancel" class="modal-cancel" aria-label="Cancel">Cancel</button>
    <h2 id="modalTitle">Upload status</h2>
    <div id="modalDesc" class="modal-body">
      <div class="spinner small" aria-hidden="true"></div>
      <div class="loading-text" id="loadingText">Uploading — please wait...</div>
      <progress id="uploadProgress" value="0" max="100">0%</progress>
    </div>
  </div>
</div>

<script src="/app/modal.js"></script>

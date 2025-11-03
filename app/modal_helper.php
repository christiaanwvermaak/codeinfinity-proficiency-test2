<?php
/**
 * Render a reusable modal markup. Simple helper for consistent modal instances.
 *
 * @param string $title Title text for the modal header
 * @param string $message Initial message displayed inside the modal body
 */
function render_modal(string $title = 'Upload status', string $message = 'Uploading — please wait...', array $options = []) : void {
  // Options: cancelLabel, confirmText, confirmYes, confirmNo, includeScript
  $cancelLabel = $options['cancelLabel'] ?? 'Cancel';
  $confirmText = $options['confirmText'] ?? 'Cancel the upload? This will stop the current upload in progress.';
  $confirmYes = $options['confirmYes'] ?? 'Yes, cancel';
  $confirmNo = $options['confirmNo'] ?? 'No, keep uploading';
  $includeScript = array_key_exists('includeScript', $options) ? (bool)$options['includeScript'] : true;

  // IDs are intentionally fixed to match the existing JS that looks up these ids
  $html = <<<HTML
<div id="uploadModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" style="position:fixed;top:0;left:0;right:0;bottom:0;display:none;pointer-events:none;z-index:99999;" data-cancel-label="{CANCEL_LABEL}" data-confirm-text="{CONFIRM_TEXT}" data-confirm-yes="{CONFIRM_YES}" data-confirm-no="{CONFIRM_NO}">
  <div class="modal-backdrop"></div>
  <div class="modal-content" role="document" aria-labelledby="modalTitle" aria-describedby="modalDesc">
    <button id="modalClose" class="modal-close" aria-label="Close">✕</button>
    <button id="modalCancel" class="modal-cancel" aria-label="Cancel">Cancel</button>
    <h2 id="modalTitle">{TITLE} <span id="modalPct" class="modal-pct" aria-hidden="true"></span></h2>
    <div id="modalDesc" class="modal-body">
      <div class="spinner small" aria-hidden="true"></div>
      <div class="loading-text" id="loadingText">{MESSAGE}</div>
      <progress id="uploadProgress" value="0" max="100">0%</progress>
    </div>
  </div>
</div>
{SCRIPT_TAG}
HTML;

  $scriptTag = $includeScript ? '<script src="/app/modal.js"></script>' : '';
  $replacements = [
    '{TITLE}' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
    '{MESSAGE}' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
    '{CANCEL_LABEL}' => htmlspecialchars($cancelLabel, ENT_QUOTES, 'UTF-8'),
    '{CONFIRM_TEXT}' => htmlspecialchars($confirmText, ENT_QUOTES, 'UTF-8'),
    '{CONFIRM_YES}' => htmlspecialchars($confirmYes, ENT_QUOTES, 'UTF-8'),
    '{CONFIRM_NO}' => htmlspecialchars($confirmNo, ENT_QUOTES, 'UTF-8'),
    '{SCRIPT_TAG}' => $scriptTag,
  ];

  $html = str_replace(array_keys($replacements), array_values($replacements), $html);
  echo $html;
}

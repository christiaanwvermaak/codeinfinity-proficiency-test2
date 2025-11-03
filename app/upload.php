<?php
// Upload page moved to upload.php
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload CSV</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <?php include __DIR__ . '/_nav.php'; ?>

  <div style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px;max-width:680px;margin:auto;">
    <div class="page-wrap">
      <form id="uploadForm" class="card" action="upload_submit.php" method="post" enctype="multipart/form-data" onsubmit="return onUploadSubmit()">
        <h1>Upload CSV</h1>
        <p class="lead">Upload a CSV file and import it into a SQLite table named <code>csv_import</code>.</p>

        <label for="csvfile">Select CSV file</label>
        <input type="file" id="csvfile" name="csvfile" accept="text/csv,text/plain" required>

        <div class="actions">
          <button type="submit" class="btn primary">Upload & Import</button>
          <a class="btn ghost" href="/">Back</a>
        </div>

        <div class="footer">Uploaded CSV will be parsed and the table recreated.</div>
      </form>
    </div>
  </div>

    <?php
      include_once __DIR__ . '/modal_helper.php';
      // render_modal(title, initialMessage, options)
      render_modal('Upload status', 'Uploading — please wait...', [
        'cancelLabel' => 'Abort',
        'confirmText' => 'Are you sure you want to stop this upload? Any progress will be lost.',
        'confirmYes' => 'Stop upload',
        'confirmNo' => 'Continue upload',
        'includeScript' => true,
      ]);
    ?>

    <script>
      function onUploadSubmit(){
        // Basic validation
        const input = document.getElementById('csvfile');
        if (!input || input.files.length === 0) {
          alert('Please select a CSV file to upload');
          return false;
        }

        // If XHR is not available for any reason, fall back to normal form submission
        if (typeof window === 'undefined' || typeof XMLHttpRequest === 'undefined') {
          return true; // allow the browser to submit the form normally
        }

        try {
          const modal = document.getElementById('uploadModal');
          if (modal) {
            modal.classList.add('open');
            document.body.classList.add('modal-open');
            modal.setAttribute('aria-hidden', 'false');
            if (window.modal && typeof window.modal.setProgress === 'function') window.modal.setProgress(0);
            if (window.modal && typeof window.modal.setMessage === 'function') window.modal.setMessage('Uploading — please wait...');
            if (window.modal && typeof window.modal.showCancel === 'function') window.modal.showCancel(true);
          }
        } catch (err) {
          // If modal initialization fails, don't block the upload — let the form submit normally
          console.warn('Modal init failed, falling back to normal submit', err);
          return true;
        }

        document.querySelectorAll('button').forEach(b=>b.disabled = true);

        const form = document.getElementById('uploadForm');
        const fd = new FormData(form);
        // generate a short token to correlate server-side import progress
        const uploadToken = Math.random().toString(36).slice(2,10) + Date.now().toString(36);
        fd.append('upload_token', uploadToken);
        // start polling import progress (server writes progress to output/upload_progress_<token>.json)
        var progressPollId = null;
        function startProgressPoll(){
          if (!uploadToken) return;
          const endpoint = 'upload_progress.php?upload_token=' + encodeURIComponent(uploadToken);
          progressPollId = setInterval(function(){
            fetch(endpoint, {cache: 'no-store'}).then(r=>r.json()).then(js=>{
              if (!js) return;
              if (js.phase === 'import' || js.phase === 'received'){
                const pct = Number(js.pct) || 0;
                if (progressEl) { progressEl.style.display = 'inline-block'; progressEl.value = pct; }
                if (window.modal && typeof window.modal.setProgress === 'function') window.modal.setProgress(pct);
                if (loadingText) loadingText.textContent = 'Importing: ' + (js.rows ? js.rows + ' rows' : '') + ' ' + pct + '%';
              } else if (js.phase === 'done'){
                if (progressEl) { progressEl.style.display = 'none'; }
                if (window.modal && typeof window.modal.setProgress === 'function') window.modal.setProgress(100);
                if (loadingText) loadingText.textContent = 'Import complete: ' + (js.rows || 0) + ' rows';
                clearInterval(progressPollId);
                progressPollId = null;
              }
            }).catch(()=>{});
          }, 400);
        }
        // keep reference so cancel can abort
        window.__activeUploadXhr = new XMLHttpRequest();
        const xhr = window.__activeUploadXhr;
  xhr.open('POST', 'upload_api.php');
        const progressEl = document.getElementById('uploadProgress');
        const loadingText = document.getElementById('loadingText');
        if (progressEl) progressEl.style.display = 'inline-block';

        xhr.upload.onprogress = function(e){
          if (!e.lengthComputable) return;
          const pct = Math.round((e.loaded / e.total) * 100);
          if (progressEl) progressEl.value = pct;
          if (loadingText) loadingText.textContent = 'Uploading: ' + pct + '%';
          if (window.modal && typeof window.modal.setProgress === 'function') window.modal.setProgress(pct);
        };

        xhr.onload = function(){
          // stop polling when server responds
          if (progressPollId) { clearInterval(progressPollId); progressPollId = null; }
          document.querySelectorAll('button').forEach(b=>b.disabled = false);
          if (progressEl) progressEl.style.display = 'none';
          const closeBtn = document.getElementById('modalClose');
          if (closeBtn) closeBtn.focus();
          window.__activeUploadXhr = null;
          if (xhr.status === 200) {
            try {
              const res = JSON.parse(xhr.responseText);
              const msg = 'Imported <strong>' + (res.rows || 0) + '</strong> rows. <a href="' + (res.db||'/output/csv_import.sqlite') + '">Download DB</a>';
              if (loadingText) loadingText.innerHTML = msg;
              if (window.modal && typeof window.modal.setMessage === 'function') window.modal.setMessage(msg);
              if (window.modal && typeof window.modal.setProgress === 'function') window.modal.setProgress(100);
            } catch (err) {
              if (loadingText) loadingText.textContent = 'Upload complete, but response was invalid.';
              if (window.modal && typeof window.modal.setMessage === 'function') window.modal.setMessage('Upload complete, but response was invalid.');
            }
          } else {
            const msg = 'Upload failed: ' + xhr.statusText + ' (' + xhr.status + ')';
            if (loadingText) loadingText.textContent = msg;
            if (window.modal && typeof window.modal.setMessage === 'function') window.modal.setMessage(msg);
            if (window.modal && typeof window.modal.setProgress === 'function') window.modal.setProgress(0);
          }
          // allow modal to be closed
          function closeModal(){
            const modal = document.getElementById('uploadModal');
            if (modal) {
              modal.classList.remove('open');
              document.body.classList.remove('modal-open');
              modal.setAttribute('aria-hidden', 'true');
            }
            document.querySelectorAll('button').forEach(b=>b.disabled = false);
          }
          if (closeBtn) closeBtn.onclick = closeModal;
          if (window.modal && typeof window.modal.showCancel === 'function') {
            window.modal.showCancel(false);
          }
          // close on Escape
          document.addEventListener('keydown', function escHandler(e){ if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', escHandler); } });
        };

        xhr.onerror = function(){
          if (progressPollId) { clearInterval(progressPollId); progressPollId = null; }
          document.querySelectorAll('button').forEach(b=>b.disabled = false);
          if (progressEl) progressEl.style.display = 'none';
          const closeBtn = document.getElementById('modalClose');
          if (closeBtn) closeBtn.focus();
          window.__activeUploadXhr = null;
          const msg = 'Upload failed due to a network error.';
          if (loadingText) loadingText.textContent = msg;
          if (window.modal && typeof window.modal.setMessage === 'function') window.modal.setMessage(msg);
          if (window.modal && typeof window.modal.setProgress === 'function') window.modal.setProgress(0);
          if (window.modal && typeof window.modal.showCancel === 'function') window.modal.showCancel(false);
          if (closeBtn) {
            closeBtn.onclick = function(){
              const modal = document.getElementById('uploadModal');
              if (modal) {
                modal.classList.remove('open');
                document.body.classList.remove('modal-open');
                modal.setAttribute('aria-hidden', 'true');
              }
              document.querySelectorAll('button').forEach(b=>b.disabled = false);
            };
          }
        };

        if (window.modal && typeof window.modal.showCancel === 'function') {
          window.modal.showCancel(true);
        }
        // start polling for server-side import progress
        startProgressPoll();
        xhr.send(fd);

        return false; // prevent default navigation — we used XHR
      }
    </script>
    <script>
      // Ensure the submit handler is attached even if inline onsubmit is ignored
      (function(){
        function attach(){
          var form = document.getElementById('uploadForm');
          if (!form) return;
          // Avoid double-binding
          if (form.__uploadSubmitAttached) return;
          form.__uploadSubmitAttached = true;
          form.addEventListener('submit', function(e){
            try {
              var r = onUploadSubmit();
              if (r === false) e.preventDefault();
            } catch (err) {
              // If handler throws, allow normal submission so user can still upload
              console.warn('onUploadSubmit error, allowing normal submit', err);
            }
          });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach); else attach();
      })();
    </script>
    
  </body>
</html>
<?php
// Handle CSV upload and import into SQLite table `csv_import`.
// Only perform import logic when the request is POST (the form on this page uses JS/AJAX).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'No file uploaded or upload error';
    exit;
  }

  $tmp = $_FILES['csvfile']['tmp_name'];
  if (!is_uploaded_file($tmp)) {
    http_response_code(400);
    echo 'Invalid upload';
    exit;
  }

  $appDir = __DIR__;
  $outDir = $appDir . '/output';
  if (!is_dir($outDir)) mkdir($outDir, 0755, true);
  $dbPath = $outDir . '/csv_import.sqlite';

  // Open CSV and read header
  $fh = fopen($tmp, 'r');
  if ($fh === false) {
    http_response_code(500);
    echo 'Failed to open uploaded file';
    exit;
  }

  $header = fgetcsv($fh, 0, ',', '"', '\\');
  if ($header === false || count($header) === 0) {
    fclose($fh);
    http_response_code(400);
    echo 'CSV appears empty or invalid';
    exit;
  }

  // Normalize header to safe column names
  $cols = array_map(function($c){
    $c = trim($c);
    $c = preg_replace('/[^A-Za-z0-9_]/', '_', $c);
    if ($c === '') $c = 'col';
    return $c;
  }, $header);

  // Ensure column names are unique and do not collide with the auto primary key 'id'
  function uniquify_columns(array $cols): array {
    $out = [];
    $seen = [];
    foreach ($cols as $c) {
      $name = $c;
      if (mb_strtolower($name, 'UTF-8') === 'id') {
        $name = $name . '_col';
      }
      $base = $name;
      $i = 1;
      while (isset($seen[mb_strtolower($name, 'UTF-8')])) {
        $name = $base . '_' . $i;
        $i++;
      }
      $seen[mb_strtolower($name, 'UTF-8')] = true;
      $out[] = $name;
    }
    return $out;
  }

  $cols = uniquify_columns($cols);

  // Create/replace SQLite DB and table
  if (file_exists($dbPath)) @unlink($dbPath);
  $db = new SQLite3($dbPath);
  $db->exec('PRAGMA synchronous = NORMAL');
  $db->exec('PRAGMA journal_mode = WAL');

  $colsSql = implode(', ', array_map(function($c){ return "\"$c\" TEXT"; }, $cols));
  $create = "CREATE TABLE IF NOT EXISTS csv_import (id INTEGER PRIMARY KEY AUTOINCREMENT, {$colsSql})";
  $db->exec($create);

  $placeholders = implode(', ', array_fill(0, count($cols), '?'));
  $insertSql = 'INSERT INTO csv_import (' . implode(',', array_map(function($c){ return "\"$c\""; }, $cols)) . ') VALUES (' . $placeholders . ')';
  $stmt = $db->prepare($insertSql);

  $rowCount = 0;
  while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
    // pad row if short
    if (count($row) < count($cols)) {
      $row = array_merge($row, array_fill(0, count($cols) - count($row), null));
    }
    // bind values
    foreach ($row as $i => $v) {
      $stmt->bindValue($i+1, $v, SQLITE3_TEXT);
    }
    $stmt->execute();
    $rowCount++;
  }

  fclose($fh);
  $db->close();

  // Return result page with a link to the DB file and row count
  $displayDb = basename($dbPath);
  echo "<!doctype html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><title>Import Result</title></head>\n<body class=\"result-page\">";
  include __DIR__ . '/_nav.php';
  echo "\n  <h1>Import complete</h1>\n  <p>Imported <strong>" . htmlspecialchars((string)$rowCount, ENT_QUOTES, 'UTF-8') . "</strong> rows into table <code>csv_import</code>.</p>\n  <p>SQLite DB: <a href=\"/output/" . htmlspecialchars($displayDb, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($displayDb, ENT_QUOTES, 'UTF-8') . "</a></p>\n  <p><a href=\"/\">Back</a></p>\n</body>\n</html>";

  exit;

}

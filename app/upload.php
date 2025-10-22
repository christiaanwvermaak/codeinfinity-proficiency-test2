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

    <div style="padding-top:18px;max-width:720px;margin:18px auto;">
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

    <div id="loadingOverlay" class="loading-overlay" role="status" aria-hidden="true">
      <div class="loading-box">
        <div class="spinner" aria-hidden="true"></div>
        <div class="loading-text" id="loadingText">Uploading — please wait...</div>
        <progress id="uploadProgress" value="0" max="100" style="display:none;width:220px;">0%</progress>
      </div>
    </div>

    <script>
      function onUploadSubmit(){
        const input = document.getElementById('csvfile');
        if (!input || input.files.length === 0) {
          alert('Please select a CSV file to upload');
          return false;
        }

        const overlay = document.getElementById('loadingOverlay');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
        document.querySelectorAll('button').forEach(b=>b.disabled = true);

        const form = document.getElementById('uploadForm');
        const fd = new FormData(form);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload_api.php');
        const progressEl = document.getElementById('uploadProgress');
        const loadingText = document.getElementById('loadingText');
        progressEl.style.display = 'inline-block';
        xhr.upload.onprogress = function(e){
          if (!e.lengthComputable) return;
          const pct = Math.round((e.loaded / e.total) * 100);
          progressEl.value = pct;
          loadingText.textContent = 'Uploading: ' + pct + '%';
        };
        xhr.onload = function(){
          document.querySelectorAll('button').forEach(b=>b.disabled = false);
          progressEl.style.display = 'none';
          if (xhr.status === 200) {
            try {
              const res = JSON.parse(xhr.responseText);
              loadingText.innerHTML = 'Imported ' + (res.rows || 0) + ' rows. <a href="' + (res.db||'/output/csv_import.sqlite') + '">Download DB</a>';
            } catch (err) {
              loadingText.textContent = 'Upload complete, but response was invalid.';
            }
          } else {
            loadingText.textContent = 'Upload failed: ' + xhr.statusText;
          }
        };
        xhr.onerror = function(){
          document.querySelectorAll('button').forEach(b=>b.disabled = false);
          progressEl.style.display = 'none';
          loadingText.textContent = 'Upload failed due to a network error.';
        };
        xhr.send(fd);

        return false; // prevent default navigation
      }
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

  $header = fgetcsv($fh);
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
  while (($row = fgetcsv($fh)) !== false) {
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
  echo "<!doctype html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><title>Import Result</title></head>\n<body style=\"font-family:Arial,Helvetica,sans-serif;padding:24px;max-width:680px;margin:auto;\">";
  include __DIR__ . '/_nav.php';
  echo "\n  <h1>Import complete</h1>\n  <p>Imported <strong>" . htmlspecialchars((string)$rowCount, ENT_QUOTES, 'UTF-8') . "</strong> rows into table <code>csv_import</code>.</p>\n  <p>SQLite DB: <a href=\"/output/" . htmlspecialchars($displayDb, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($displayDb, ENT_QUOTES, 'UTF-8') . "</a></p>\n  <p><a href=\"/\">Back</a></p>\n</body>\n</html>";

  exit;

}

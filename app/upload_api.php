<?php
// CSV upload API: imports uploaded CSV into SQLite table `csv_import` and returns JSON
header('Content-Type: application/json; charset=utf-8');

// Set strict error handling: convert errors to exceptions and log uncaught exceptions
ini_set('display_errors', '0');
error_reporting(E_ALL);

function log_api_error(Throwable $e) {
    $logFile = __DIR__ . '/output/error.log';
    $msg = '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    @file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
}

function log_api_info(string $msg) {
    $logFile = __DIR__ . '/output/upload.log';
    $entry = '[' . date('c') . '] ' . $msg . "\n";
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    log_api_error($e);
    http_response_code(500);
    echo json_encode(['error' => 'Server error during CSV import']);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$tmp = $_FILES['csvfile']['tmp_name'];
if (!is_uploaded_file($tmp)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid upload']);
    exit;
}

$appDir = __DIR__;
$outDir = $appDir . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
// Tighten permissions and ensure writable
@chmod($outDir, 0755);
if (!is_writable($outDir)) {
    log_api_error(new RuntimeException('Output directory not writable: ' . $outDir));
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: output directory not writable']);
    exit;
}
$dbPath = $outDir . '/csv_import.sqlite';

// Log upload start with basic file info
try {
    $info = [];
    if (isset($_FILES['csvfile'])) {
        $info['name'] = $_FILES['csvfile']['name'] ?? '';
        $info['size'] = $_FILES['csvfile']['size'] ?? 0;
        $info['tmp'] = $_FILES['csvfile']['tmp_name'] ?? '';
    }
    $info['remote'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    log_api_info('Upload started: ' . json_encode($info));
} catch (Throwable $e) {
    // never fail the request due to logging
}

// Optional upload token to track progress across requests
$token = null;
if (isset($_POST['upload_token'])) {
    $token = preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['upload_token']);
}
$progressFile = $token ? ($outDir . '/upload_progress_' . $token . '.json') : null;
// helper to write progress (best-effort)
if ($progressFile) {
    @file_put_contents($progressFile, json_encode(['phase' => 'received', 'pct' => 0]), LOCK_EX);
}

$fh = fopen($tmp, 'r');
if ($fh === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to open uploaded file']);
    exit;
}

 $header = fgetcsv($fh, 0, ',', '"', '\\');
if ($header === false || count($header) === 0) {
    fclose($fh);
    http_response_code(400);
    echo json_encode(['error' => 'CSV appears empty or invalid']);
    exit;
}

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
        // avoid collision with primary key 'id' (case-insensitive)
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

if (!class_exists('SQLite3')) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: PHP SQLite3 extension is not available']);
    fclose($fh);
    exit;
}

// Remove previous DB if present (guarded)
if (is_file($dbPath)) {
    if (!is_writable($dbPath)) {
        @chmod($dbPath, 0644);
    }
    $deleted = @unlink($dbPath);
    if (!$deleted) {
        $err = error_get_last();
        if ($err) {
            log_api_error(new ErrorException($err['message'], 0, E_WARNING, $err['file'] ?? __FILE__, $err['line'] ?? __LINE__));
        }
    }
}

try {
    $db = new SQLite3($dbPath);
    // set a busy timeout to reduce locking failures when available
    if (method_exists($db, 'busyTimeout')) {
        $db->busyTimeout(5000);
    }
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('PRAGMA journal_mode = WAL');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create SQLite DB: ' . $e->getMessage()]);
    fclose($fh);
    exit;
}

$colsSql = implode(', ', array_map(function($c){ return '"' . $c . '" TEXT'; }, $cols));
$create = "CREATE TABLE IF NOT EXISTS csv_import (id INTEGER PRIMARY KEY AUTOINCREMENT, {$colsSql})";
$db->exec($create);

$placeholders = implode(', ', array_fill(0, count($cols), '?'));
$insertSql = 'INSERT INTO csv_import (' . implode(',', array_map(function($c){ return '"' . $c . '"'; }, $cols)) . ') VALUES (' . $placeholders . ')';
$stmt = $db->prepare($insertSql);

$rowCount = 0;
$lastUpdate = microtime(true);
// Wrap inserts in a transaction for performance and safety
$startTime = microtime(true);
try {
    $db->exec('BEGIN');
    $totalBytes = @filesize($tmp) ?: 0;
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (count($row) < count($cols)) {
            $row = array_merge($row, array_fill(0, count($cols) - count($row), null));
        }
        foreach ($row as $i => $v) {
            $stmt->bindValue($i+1, $v, SQLITE3_TEXT);
        }
        $stmt->execute();
        $rowCount++;
        // update progress file periodically (every 50 rows or 0.5s)
        if ($progressFile && ($rowCount % 50 === 0 || (microtime(true) - $lastUpdate) > 0.5)) {
            $lastUpdate = microtime(true);
            $bytesRead = @ftell($fh) ?: 0;
            $pct = $totalBytes > 0 ? (int) min(99, floor(($bytesRead / $totalBytes) * 100)) : 0;
            @file_put_contents($progressFile, json_encode(['phase' => 'import', 'pct' => $pct, 'rows' => $rowCount]), LOCK_EX);
        }
    }
    $db->exec('COMMIT');
} catch (Throwable $e) {
    try { $db->exec('ROLLBACK'); } catch (Throwable $__) {}
    http_response_code(500);
    log_api_error($e);
    echo json_encode(['error' => 'Failed during import: ' . $e->getMessage()]);
    fclose($fh);
    if (isset($db)) $db->close();
    exit;
}

// final progress write
if ($progressFile) {
    @file_put_contents($progressFile, json_encode(['phase' => 'done', 'pct' => 100, 'rows' => $rowCount]), LOCK_EX);
}

fclose($fh);
$db->close();

// Ensure DB file permissions are reasonable
@chmod($dbPath, 0644);

$duration = round(microtime(true) - $startTime, 3);
try { log_api_info('Import complete: rows=' . $rowCount . ' db=' . $dbPath . ' duration=' . $duration . 's'); } catch (Throwable $__) {}

echo json_encode(['rows' => $rowCount, 'db' => '/output/' . basename($dbPath)]);
// Attempt to delete the uploaded temporary file to avoid leaving large files on disk.
try {
    // $_FILES['csvfile']['tmp_name'] may be the same as $tmp; prefer using $tmp variable
    if (isset($tmp) && is_file($tmp)) {
        // ensure it's removable and avoid raising warnings
        if (!is_writable($tmp)) {
            @chmod($tmp, 0644);
        }
        $ok = @unlink($tmp);
        if ($ok) {
            try { log_api_info('Uploaded file deleted: ' . $tmp); } catch (Throwable $__e) {}
        } else {
            // log but don't fail the request
            try { log_api_info('Failed to delete uploaded file: ' . $tmp); } catch (Throwable $__e) {}
        }
    }
} catch (Throwable $e) {
    // best-effort: log and continue
    try { log_api_error($e); } catch (Throwable $__e) {}
}

    // Attempt to remove progress JSON file after successful import to avoid leaving stale state
    if (isset($progressFile) && $progressFile && is_file($progressFile)) {
        try {
            if (!is_writable($progressFile)) {
                @chmod($progressFile, 0644);
            }
            $pfOk = @unlink($progressFile);
            if ($pfOk) {
                try { log_api_info('Progress file deleted: ' . $progressFile); } catch (Throwable $__e) {}
            } else {
                try { log_api_info('Failed to delete progress file: ' . $progressFile); } catch (Throwable $__e) {}
            }
        } catch (Throwable $e) {
            try { log_api_error($e); } catch (Throwable $__e) {}
        }
    }

    exit;

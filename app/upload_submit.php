<?php
// Handle CSV upload and import into SQLite table `csv_import`.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

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

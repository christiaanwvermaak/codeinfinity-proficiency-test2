<?php
// CSV upload API: imports uploaded CSV into SQLite table `csv_import` and returns JSON
header('Content-Type: application/json; charset=utf-8');

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
$dbPath = $outDir . '/csv_import.sqlite';

$fh = fopen($tmp, 'r');
if ($fh === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to open uploaded file']);
    exit;
}

$header = fgetcsv($fh);
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

if (file_exists($dbPath)) @unlink($dbPath);
$db = new SQLite3($dbPath);
$db->exec('PRAGMA synchronous = NORMAL');
$db->exec('PRAGMA journal_mode = WAL');

$colsSql = implode(', ', array_map(function($c){ return '"' . $c . '" TEXT'; }, $cols));
$create = "CREATE TABLE IF NOT EXISTS csv_import (id INTEGER PRIMARY KEY AUTOINCREMENT, {$colsSql})";
$db->exec($create);

$placeholders = implode(', ', array_fill(0, count($cols), '?'));
$insertSql = 'INSERT INTO csv_import (' . implode(',', array_map(function($c){ return '"' . $c . '"'; }, $cols)) . ') VALUES (' . $placeholders . ')';
$stmt = $db->prepare($insertSql);

$rowCount = 0;
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < count($cols)) {
        $row = array_merge($row, array_fill(0, count($cols) - count($row), null));
    }
    foreach ($row as $i => $v) {
        $stmt->bindValue($i+1, $v, SQLITE3_TEXT);
    }
    $stmt->execute();
    $rowCount++;
}

fclose($fh);
$db->close();

echo json_encode(['rows' => $rowCount, 'db' => '/output/' . basename($dbPath)]);
exit;

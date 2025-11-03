<?php
// Simple progress endpoint for uploads that writes/reads a small JSON file per upload token
header('Content-Type: application/json; charset=utf-8');
$appDir = __DIR__;
$outDir = $appDir . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
$token = $_GET['upload_token'] ?? '';
$token = preg_replace('/[^A-Za-z0-9_-]/', '', $token);
$progressFile = $outDir . '/upload_progress_' . $token . '.json';
if (!$token || !is_file($progressFile)) {
    echo json_encode(['phase' => 'unknown', 'pct' => 0]);
    exit;
}
$raw = @file_get_contents($progressFile);
if (!$raw) { echo json_encode(['phase' => 'unknown', 'pct' => 0]); exit; }
$data = json_decode($raw, true);
if (!is_array($data)) { echo json_encode(['phase' => 'unknown', 'pct' => 0]); exit; }
echo json_encode($data);
exit;

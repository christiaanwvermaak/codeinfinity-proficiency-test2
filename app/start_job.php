<?php
// start_job.php
// Starts the CLI worker for a given job file. Expects POST { job: '/full/path/to/job.json' }
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$inp = json_decode(file_get_contents('php://input'), true);
if (!is_array($inp) || empty($inp['job'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing job parameter']);
    exit;
}

$jobFile = $inp['job'];
// Simple validation: job must exist under ./output/jobs/
$appDir = __DIR__;
$jobsDir = $appDir . '/output/jobs';
$realJobsDir = realpath($jobsDir);
$realJob = realpath($jobFile);
if ($realJobsDir === false || $realJob === false || strpos($realJob, $realJobsDir) !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid job path']);
    exit;
}

$phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$cli = escapeshellarg($phpBin) . ' ' . escapeshellarg($appDir . '/generate_cli.php') . ' --job ' . escapeshellarg($realJob);
$logFile = $appDir . '/output/jobs/' . basename($realJob) . '.log';
$cmd = $cli . ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
// exec to start background process and capture PID
exec($cmd, $out, $rc);
$pid = isset($out[0]) ? (int)$out[0] : null;

if ($rc !== 0) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to start worker', 'rc' => $rc]);
    exit;
}

echo json_encode(['ok' => true, 'pid' => $pid, 'log' => '/output/jobs/' . basename($realJob) . '.log']);

exit;

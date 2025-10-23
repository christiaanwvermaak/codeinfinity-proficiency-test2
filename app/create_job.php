<?php
// API to create a generation job and return job/output paths as JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$count = isset($_POST['count']) ? (int) $_POST['count'] : 0;
if ($count < 1 || $count > 4000000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid count']);
    exit;
}

$appDir = __DIR__;
$outDir = $appDir . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
$jobsDir = $outDir . '/jobs';
if (!is_dir($jobsDir)) mkdir($jobsDir, 0755, true);

// default name pools (match generate.php)
$first_names = [
    "James","Mary","John","Patricia","Robert","Jennifer","Michael","Linda",
    "William","Elizabeth","David","Barbara","Richard","Susan","Joseph","Jessica",
    "Thomas","Sarah","Charles","Karen"
];
$last_names = [
    "Smith","Johnson","Williams","Brown","Jones","Garcia","Miller","Davis",
    "Rodriguez","Martinez","Hernandez","Lopez","Gonzalez","Wilson","Anderson","Thomas",
    "Taylor","Moore","Jackson","Martin"
];

$job = [
    'count' => $count,
    'output' => 'output/output_' . time() . '_' . bin2hex(random_bytes(4)) . '.csv',
    'first_names' => $first_names,
    'last_names' => $last_names,
    'created_at' => (new DateTimeImmutable())->format(DateTime::ATOM),
];

$jobFile = $jobsDir . '/job_' . time() . '_' . bin2hex(random_bytes(4)) . '.json';
file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$res = [
    'ok' => true,
    'job' => $jobFile,
    'job_web' => '/output/jobs/' . basename($jobFile),
    'out_url' => '/' . ltrim($job['output'], '/'),
];

echo json_encode($res);
exit;

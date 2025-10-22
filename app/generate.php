<?php
// Add an array with twenty first names
$first_names = [
    "James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda",
    "William", "Elizabeth", "David", "Barbara", "Richard", "Susan", "Joseph", "Jessica",
    "Thomas", "Sarah", "Charles", "Karen"
];

// Add an array with twenty last names
$last_names = [
    "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis",
    "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas",
    "Taylor", "Moore", "Jackson", "Martin"
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$count = isset($_POST['count']) ? (int) $_POST['count'] : 0;
if ($count < 1 || $count > 4000000) {
    http_response_code(400);
    echo 'Invalid count';
    exit;
}

// Allow long-running generation from web requests. For very large counts
// you should run a CLI worker instead — this prevents the default 30s
// PHP execution time limit from aborting the script.
@set_time_limit(0);
@ini_set('max_execution_time', '0');
// Continue running even if the client disconnects
ignore_user_abort(true);

// Helper to generate fake person data (lightweight)
function random_name($len = 6){
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    $res = '';
    for ($i=0;$i<$len;$i++) $res .= $chars[random_int(0, strlen($chars)-1)];
    return ucfirst($res);
}


// Enqueue a job for the CLI worker. Create a jobs folder under output and write a JSON job file
$appDir = __DIR__;
$outDir = $appDir . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
$jobsDir = $outDir . '/jobs';
if (!is_dir($jobsDir)) mkdir($jobsDir, 0755, true);

$job = [
    'count' => $count,
    'output' => 'output/output.csv',
    'first_names' => $first_names,
    'last_names' => $last_names,
    'created_at' => (new DateTimeImmutable())->format(DateTime::ATOM),
];

$jobFile = $jobsDir . '/job_' . time() . '_' . bin2hex(random_bytes(4)) . '.json';
file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Do not attempt to spawn background processes from the web request (can block or be disallowed).
// Instead, return the queued page and show the exact CLI command to run the worker for this job.
$relativePath = 'output/output.csv';
$displayJob = basename($jobFile);
$phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$cliScript = __DIR__ . '/generate_cli.php';
$exampleCmd = $phpBin . ' ' . $cliScript . ' --job ' . $jobFile;

echo "<!doctype html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Job queued</title></head>\n<body style=\"font-family:Arial,Helvetica,sans-serif;padding:24px;max-width:680px;margin:auto;\">";
include __DIR__ . '/_nav.php';
echo "\n  <h1>Job queued</h1>\n  <p>Your generation job has been queued. To run jobs, execute the CLI worker with the command below (on the host/container):</p>\n  <pre style=\"background:#0b1220;padding:12px;border-radius:8px;color:#cfeaf6;\">" . htmlspecialchars($exampleCmd, ENT_QUOTES, 'UTF-8') . "</pre>\n  <ul>\n    <li>Job file: <a href=\"/output/jobs/" . htmlspecialchars($displayJob, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($displayJob, ENT_QUOTES, 'UTF-8') . "</a></li>\n    <li>Expected CSV (when ready): <a href=\"/".htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8')."\">output.csv</a></li>\n  </ul>\n  <p><a href=\"/\">Back</a></p>\n</body>\n</html>";
exit;

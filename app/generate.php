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
    // use unique per-job output to avoid collisions
    'output' => 'output/output_' . time() . '_' . bin2hex(random_bytes(4)) . '.csv',
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
$outRel = $job['output'];
$outUrl = '/' . ltrim($outRel, '/');

echo "<!doctype html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Job queued</title></head>\n<body style=\"font-family:Inter,system-ui,Arial,Helvetica,sans-serif;padding:24px;max-width:680px;margin:auto;\">";
include __DIR__ . '/_nav.php';
?>

    <h1>Job queued</h1>
    <p>Your generation job has been queued. It will be started automatically and this page will show a download link when the CSV is ready.</p>
    <ul>
        <li>Job file: <a id="jobLink" href="/output/jobs/<?php echo htmlspecialchars(basename($jobFile), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(basename($jobFile), ENT_QUOTES, 'UTF-8'); ?></a></li>
        <li id="statusItem">Status: <em>queued</em></li>
    </ul>
    <div id="downloadArea"></div>
    <p><a href="/">Back</a></p>

    <script>
        const JOB_FILE = <?php echo json_encode($jobFile, JSON_UNESCAPED_SLASHES); ?>;
        const JOB_WEB = <?php echo json_encode('/output/jobs/' . basename($jobFile), JSON_UNESCAPED_SLASHES); ?>;
        const OUT_URL = <?php echo json_encode($outUrl, JSON_UNESCAPED_SLASHES); ?>;

        async function startJob() {
            const res = await fetch('start_job.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ job: JOB_FILE })
            });
            return res.json();
        }

        async function fileExists(url) {
            try {
                const r = await fetch(url, { method: 'HEAD' });
                return r.status === 200;
            } catch (e) {
                return false;
            }
        }

        (async function(){
            const statusEl = document.getElementById('statusItem');
            const downloadArea = document.getElementById('downloadArea');
            statusEl.innerHTML = 'Status: <em>starting</em>';

            let info;
            try {
                info = await startJob();
            } catch (err) {
                statusEl.innerHTML = 'Status: <em>error</em>';
                downloadArea.innerHTML = '<p style="color:salmon">Failed to start job: ' + String(err) + '</p>';
                return;
            }

            if (!info || !info.ok) {
                statusEl.innerHTML = 'Status: <em>failed</em>';
                downloadArea.innerHTML = '<p style="color:salmon">Start failed: ' + (info && info.error ? info.error : 'unknown') + '</p>';
                return;
            }

            statusEl.innerHTML = 'Status: <em>running (pid ' + (info.pid || 'n/a') + ')</em>';

            // Poll for the file
            while (true) {
                if (await fileExists(OUT_URL)) break;
                await new Promise(r => setTimeout(r, 1500));
            }

            statusEl.innerHTML = 'Status: <em>completed</em>';
            downloadArea.innerHTML = '<p><a class="btn primary" href="' + OUT_URL + '" download>Download CSV</a></p>' +
                '<p>Log: <a href="' + JOB_WEB + '.log">View log</a></p>';
        })();
    </script>

</body>
</html>

<?php
exit;

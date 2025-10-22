#!/usr/bin/env php
<?php
// CLI CSV generator worker for large runs.
// Usage: php app/generate_cli.php <count> [output.csv]

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the CLI.\n");
    exit(1);
}

$argv0 = $argv[0] ?? 'generate_cli.php';

// Parse args: either `php generate_cli.php <count> [output.csv]` or
// `php generate_cli.php --job /path/to/job.json`
$jobFile = null;
$count = null;
$outRel = 'output/output.csv';

if (isset($argv[1])) {
    if ($argv[1] === '--job' && isset($argv[2])) {
        $jobFile = $argv[2];
    } elseif (is_file($argv[1])) {
        // allow passing job file directly
        $jobFile = $argv[1];
    } elseif (is_numeric($argv[1])) {
        $count = (int)$argv[1];
        $outRel = $argv[2] ?? $outRel;
    }
}

$appDir = __DIR__;
$outDir = $appDir . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

// If job file provided, read settings from it
$first_names = null;
$last_names = null;
if ($jobFile !== null) {
    if (!is_file($jobFile)) {
        fwrite(STDERR, "Job file not found: {$jobFile}\n");
        exit(2);
    }
    $raw = file_get_contents($jobFile);
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['count'])) {
        fwrite(STDERR, "Invalid job file: {$jobFile}\n");
        exit(2);
    }
    $count = (int)$data['count'];
    if (!empty($data['output'])) $outRel = $data['output'];
    if (!empty($data['first_names']) && is_array($data['first_names'])) $first_names = $data['first_names'];
    if (!empty($data['last_names']) && is_array($data['last_names'])) $last_names = $data['last_names'];
}

if ($count === null) {
    fwrite(STDOUT, "Usage: php {$argv0} <count> [output.csv]  OR php {$argv0} --job /path/to/job.json\n");
    exit(1);
}

if ($count < 1 || $count > 4000000) {
    fwrite(STDERR, "count must be between 1 and 4000000\n");
    exit(2);
}

$filePath = $appDir . '/' . ltrim($outRel, '/');

$dbPath = $outDir . '/seen.sqlite';
$db = new SQLite3($dbPath);
$db->exec('PRAGMA journal_mode = WAL');
$db->exec('CREATE TABLE IF NOT EXISTS seen(key TEXT PRIMARY KEY)');
$insertStmt = $db->prepare('INSERT OR IGNORE INTO seen(key) VALUES (:k)');

// Names pools (default, but may be overridden by job file)
if ($first_names === null) {
    $first_names = [
        "James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda",
        "William", "Elizabeth", "David", "Barbara", "Richard", "Susan", "Joseph", "Jessica",
        "Thomas", "Sarah", "Charles", "Karen"
    ];
}
if ($last_names === null) {
    $last_names = [
        "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis",
        "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas",
        "Taylor", "Moore", "Jackson", "Martin"
    ];
}

// Helpers
function pick_firsts(array $pool): array {
    $num = random_int(1,3);
    $out = [];
    for ($i=0;$i<$num;$i++) $out[] = $pool[array_rand($pool)];
    return $out;
}

function compute_dob_for_age(int $age): string {
    $today = new DateTimeImmutable();
    $max = $today->modify("-{$age} years");
    $min = $today->modify('-'.($age+1).' years')->modify('+1 day');
    $minTs = $min->getTimestamp();
    $maxTs = $max->getTimestamp();
    if ($minTs > $maxTs) $minTs = $maxTs - 86400;
    $randTs = random_int($minTs, $maxTs);
    return (new DateTimeImmutable())->setTimestamp($randTs)->format('d/m/Y');
}

$out = fopen($filePath, 'w');
if ($out === false) {
    fwrite(STDERR, "Failed to open output file: {$filePath}\n");
    exit(3);
}

// write header
fputcsv($out, ['ID','Name','Surname','Initials','Age','DateOfBirth'], ',', '"', "\\");

// Generation loop with SQLite-backed uniqueness
$maxAttempts = 50;
for ($i=1; $i<=$count; $i++) {
    $chosenFirsts = pick_firsts($first_names);
    $first_full = implode(' ', $chosenFirsts);
    $initialsArr = array_map(function($n){ return strtoupper(mb_substr($n,0,1)); }, $chosenFirsts);
    $initials = implode('', $initialsArr);
    $last = $last_names[array_rand($last_names)];

    $attempts = 0;
    $inserted = false;
    do {
        $age = random_int(18, 90);
        $dob = compute_dob_for_age($age);

        $key = mb_strtolower($first_full, 'UTF-8') . '|' . mb_strtolower($last, 'UTF-8') . '|' . $age . '|' . $dob;

        // attempt to insert; if changes() === 1 it's newly inserted and unique
        $insertStmt->bindValue(':k', $key, SQLITE3_TEXT);
        $res = $insertStmt->execute();
        // sqlite3->changes() indicates whether the insert occurred
        if ($db->changes() === 1) {
            $inserted = true;
        }

        $attempts++;
        if ($attempts > $maxAttempts) break;
    } while (!$inserted);

    // write the row regardless (if duplicate case after giving up, it's acceptable)
    fputcsv($out, [$i, $first_full, $last, $initials, $age, $dob], ',', '"', "\\");

    // progress indicator for large runs
    if ($i % 10000 === 0) {
        fwrite(STDOUT, "Generated {$i}/{$count} rows...\n");
    }
}

fclose($out);
$db->close();

fwrite(STDOUT, "Done. Wrote {$count} rows to {$filePath}\n");
exit(0);

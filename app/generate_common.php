<?php
// Shared code for generators
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the CLI.\n");
    exit(1);
}

$argv0 = $argv[0] ?? 'generate.php';

$jobFile = null;
$count = null;
$outRel = 'output/output.csv';

if (isset($argv[1])) {
    if ($argv[1] === '--job' && isset($argv[2])) {
        $jobFile = $argv[2];
    } elseif (is_file($argv[1])) {
        $jobFile = $argv[1];
    } elseif (is_numeric($argv[1])) {
        $count = (int)$argv[1];
        $outRel = $argv[2] ?? $outRel;
    }
}

$appDir = __DIR__;
$outDir = $appDir . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

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
}

if ($count === null) {
    fwrite(STDOUT, "Usage: php {$argv0} <count> [output.csv]\n");
    exit(1);
}

if ($count < 1 || $count > 4000000) {
    fwrite(STDERR, "count must be between 1 and 4000000\n");
    exit(2);
}

$filePath = $appDir . '/' . ltrim($outRel, '/');

// Names pools
$first_names = [
    "James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda",
    "William", "Elizabeth", "David", "Barbara", "Richard", "Susan", "Joseph", "Jessica",
    "Thomas", "Sarah", "Charles", "Karen"
];
$last_names = [
    "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis",
    "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas",
    "Taylor", "Moore", "Jackson", "Martin"
];

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

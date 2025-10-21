<?php
// Simple CSV generator (non-PDO SQLite, just generates data)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$count = isset($_POST['count']) ? (int) $_POST['count'] : 0;
if ($count < 1 || $count > 10000) {
    http_response_code(400);
    echo 'Invalid count';
    exit;
}

// Helper to generate fake person data (lightweight)
function random_name($len = 6){
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    $res = '';
    for ($i=0;$i<$len;$i++) $res .= $chars[random_int(0, strlen($chars)-1)];
    return ucfirst($res);
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="people_' . time() . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['id','first_name','last_name','email','age']);

for ($i=1;$i<=$count;$i++){
    $first = random_name(random_int(4,8));
    $last  = random_name(random_int(4,10));
    $email = strtolower($first) . '.' . strtolower($last) . $i . '@example.com';
    $age = random_int(18, 90);
    fputcsv($out, [$i, $first, $last, $email, $age]);
}

fclose($out);
exit;

<?php
// Segunda passada de limpeza
$files = [
    'app/Models/Patient.php'
];

foreach ($files as $f) {
    if (file_exists($f)) {
        unlink($f);
    }
}

$migrationsDir = 'database/migrations';
if (is_dir($migrationsDir)) {
    $migs = scandir($migrationsDir);
    foreach ($migs as $m) {
        if (strpos($m, 'add_council_number_token') !== false) {
            unlink("$migrationsDir/$m");
        }
    }
}
echo "OK\n";

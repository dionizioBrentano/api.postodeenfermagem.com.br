<?php
$f = 'app/Http/Controllers/PatientController.php';
if (file_exists($f)) unlink($f);
echo "OK\n";

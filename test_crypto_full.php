<?php

echo "Iniciando exclusao e reversao do Sprint...\n";

// 1. Deletar arquivos criados no sprint
$filesToDelete = [
    'app/Services/CryptoService.php',
    'app/Services/TokenizationService.php',
    'app/Casts/EncryptedWithDek.php',
    'app/Models/RecordEncryptionKey.php',
    'app/Models/AuditLog.php',
    'app/Traits/Auditable.php',
    'app/Http/Controllers/DebugPatientController.php',
    'app/Http\Controllers/LogViewerController.php',
    'test_decrypt_manual.php',
    'test_decrypt_tinker.php',
    'test_db_direct.php',
];

foreach ($filesToDelete as $file) {
    if (file_exists($file)) {
        unlink($file);
        echo "Deletado: $file\n";
    }
}

// Encontrar e deletar migrations especificas
$migrationsDir = 'database/migrations';
$migrations = scandir($migrationsDir);
foreach ($migrations as $m) {
    if (strpos($m, 'create_record_encryption_keys_table') !== false || 
        strpos($m, 'create_audit_logs_table') !== false ||
        strpos($m, 'create_patients_table') !== false) {
        unlink("$migrationsDir/$m");
        echo "Deletada migration: $m\n";
    }
}

// 2. Reverter routes/api.php
$apiRoutesPath = 'routes/api.php';
if (file_exists($apiRoutesPath)) {
    $apiRoutes = file_get_contents($apiRoutesPath);
    $apiRoutes = preg_replace('/Route::get\(\'\/debug-patient\'.*?\}\);/s', '', $apiRoutes);
    $apiRoutes = preg_replace('/Route::get\(\'\/logs\'.*?\}\);/s', '', $apiRoutes);
    file_put_contents($apiRoutesPath, $apiRoutes);
    echo "Revertido: routes/api.php\n";
}

// 3. Reverter frontend/src/App.jsx
$appJsxPath = '../postodeenfermagem.com.br/frontend/src/App.jsx';
if (file_exists($appJsxPath)) {
    $appJsx = file_get_contents($appJsxPath);
    // Remover botões de log e debug
    $appJsx = preg_replace('/<button[^>]*onClick=\{testDek\}[^>]*>.*?<\/button>/s', '', $appJsx);
    $appJsx = preg_replace('/<button[^>]*onClick=\{testScope\}[^>]*>.*?<\/button>/s', '', $appJsx);
    $appJsx = preg_replace('/<button[^>]*onClick=\{fetchLogs\}[^>]*>.*?<\/button>/s', '', $appJsx);
    $appJsx = preg_replace('/<div[^>]*className="debug-section"[^>]*>.*?<\/div>/s', '', $appJsx);
    // Remover importacoes ou definicoes dessas funcoes se necessario, mas remover os botoes ja limpa a UI.
    file_put_contents($appJsxPath, $appJsx);
    echo "Revertido: frontend/src/App.jsx\n";
}

echo "Limpeza concluida.\n";

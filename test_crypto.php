<?php
$crypto = app(\App\Services\CryptoService::class);
$dek = $crypto->getOrCreateDek('test-uuid-1234');
echo "DEK: " . base64_encode($dek) . "\n";

$encrypted = $crypto->encrypt("12345678900", $dek);
echo "Encrypted: " . $encrypted . "\n";

$decrypted = $crypto->decrypt($encrypted, $dek);
echo "Decrypted: " . ($decrypted === null ? 'NULL (FAILED)' : $decrypted) . "\n";

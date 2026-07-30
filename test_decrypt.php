<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cryptoService = app(\App\Services\CryptoService::class);
$dekStr = "eyJpdiI6IkV5NjA4cWFaWUcyb2pjOExzNnR3ckE9PSIsInZhbHVlIjoiQ1ljVENRS1R6MkQyUlVuZEkzRWc5MHFuMkxCK3JKbTI3dmtkQVUyNzZ3V3NObW4xY2Rjc05B NWxJclRIUm9BdCIsIm1hYyI6IjU3M2Y1MWMxNGJmNTNmM2IyZWI3MGY5OGFhMDFmMGU2ZGY0NWMzMmQzNTRmZDRhMzRiNzQ4MTQ4NTNlNTBjMjUiLCJ0YWciOiIifQ==";
// fix the space in base64 if any
$dekStr = str_replace(' ', '', $dekStr);

$kekEncrypter = new \Illuminate\Encryption\Encrypter(env('APP_KEY'), config('app.cipher'));
$dek = $kekEncrypter->decrypt($dekStr);

echo "DEK Decrypted Length: " . strlen($dek) . "\n";

$rawCpf = "Rzy1mq9NH+Sv5mXkB6J9zkl0GPUDRqKonJ1Itl1+YEH6fnEHWsA9";
$decryptedCpf = $cryptoService->decrypt($rawCpf, $dek);
echo "Decrypted CPF: " . var_export($decryptedCpf, true) . "\n";

$rawCns = "uvsyJ6D7JaPXkufZOzCmE4vpFvBbfMwfiKpzqXEjf0jq4JqHSTNzVnrJuA==";
$decryptedCns = $cryptoService->decrypt($rawCns, $dek);
echo "Decrypted CNS: " . var_export($decryptedCns, true) . "\n";

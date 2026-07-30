<?php
$dek = random_bytes(32);
$plainText = "12345678900";

$iv = random_bytes(12);
$tag = '';
$ciphertext = openssl_encrypt(
    $plainText,
    'aes-256-gcm',
    $dek,
    OPENSSL_RAW_DATA,
    $iv,
    $tag,
    '',
    16
);

$encoded = base64_encode($iv . $ciphertext . $tag);

$decoded = base64_decode($encoded);
$ex_iv = substr($decoded, 0, 12);
$ex_tag = substr($decoded, -16);
$ex_ciphertext = substr($decoded, 12, -16);

$decrypted = openssl_decrypt(
    $ex_ciphertext,
    'aes-256-gcm',
    $dek,
    OPENSSL_RAW_DATA,
    $ex_iv,
    $ex_tag
);

echo "Decrypted: " . ($decrypted === $plainText ? "SUCCESS" : "FAIL") . "\n";
if ($decrypted === false) {
    while ($msg = openssl_error_string()) {
        echo "Error: $msg\n";
    }
}

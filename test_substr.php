<?php
$iv = str_repeat('A', 12);
$ciphertext = "MYCIPHERTEXT";
$tag = str_repeat('B', 16);

$decoded = $iv . $ciphertext . $tag;

$ex_iv = substr($decoded, 0, 12);
$ex_tag = substr($decoded, -16);
$ex_ciphertext = substr($decoded, 12, -16);

echo "IV: " . ($iv === $ex_iv ? "OK" : "FAIL") . "\n";
echo "TAG: " . ($tag === $ex_tag ? "OK" : "FAIL") . "\n";
echo "CIPHERTEXT: " . ($ciphertext === $ex_ciphertext ? "OK" : "FAIL") . "\n";

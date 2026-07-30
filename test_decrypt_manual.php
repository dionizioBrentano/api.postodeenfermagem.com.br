<?php
$cpf = "Rzy1mq9NH+Sv5mXkB6J9zkl0GPUDRqKonJ1Itl1+YEH6fnEHWsA9";
$decoded = base64_decode($cpf);

$iv = substr($decoded, 0, 12);
$tag = substr($decoded, -16);
$ciphertext = substr($decoded, 12, -16);

echo "IV (hex): " . bin2hex($iv) . "\n";
echo "TAG (hex): " . bin2hex($tag) . "\n";
echo "CIPHERTEXT (hex): " . bin2hex($ciphertext) . "\n";

<?php
$cpf = "Rzy1mq9NH+Sv5mXkB6J9zkl0GPUDRqKonJ1Itl1+YEH6fnEHWsA9";
$decoded = base64_decode($cpf);
echo "CPF Length: " . strlen($decoded) . "\n";

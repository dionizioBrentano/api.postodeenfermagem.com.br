<?php
$tenant = \App\Models\Tenant::first();
if (!$tenant) {
    echo "No tenant found\n";
    exit;
}

$patient = clone \App\Models\Patient::create([
    'tenant_id' => $tenant->id,
    'name' => 'Maria Silva',
    'cpf' => '99999999999',
    'cns' => '888888888888888',
]);

echo "Patient created with ID: " . $patient->id . "\n";
echo "Encrypted CPF: " . $patient->getRawOriginal('cpf') . "\n";

$read = \App\Models\Patient::find($patient->id);
echo "Decrypted CPF: " . $read->cpf . "\n";
echo "Decrypted CNS: " . $read->cns . "\n";

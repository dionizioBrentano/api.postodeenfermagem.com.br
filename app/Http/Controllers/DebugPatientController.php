<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebugPatientController extends Controller
{
    public function index()
    {
        try {
            $patient = DB::table('patients')->first();
            if (!$patient) {
                return response()->json(['error' => 'No patient found']);
            }

            $dekRecord = DB::table('record_encryption_keys')->where('record_id', $patient->id)->first();

            $debugDecryptedCpf = null;
            $opensslError = null;

            if ($dekRecord) {
                try {
                    $cryptoService = app(\App\Services\CryptoService::class);
                    $dek = $cryptoService->getDek($patient->id);
                    
                    // Manual decrypt logic
                    $decoded = base64_decode($patient->cpf);
                    $iv = substr($decoded, 0, 12);
                    $tag = substr($decoded, -16);
                    $ciphertext = substr($decoded, 12, -16);

                    $plainText = openssl_decrypt(
                        $ciphertext,
                        'aes-256-gcm',
                        $dek,
                        OPENSSL_RAW_DATA,
                        $iv,
                        $tag
                    );

                    if ($plainText === false) {
                        $opensslError = [];
                        while ($msg = openssl_error_string()) {
                            $opensslError[] = $msg;
                        }
                    } else {
                        $debugDecryptedCpf = $plainText;
                    }
                } catch (\Exception $ex) {
                    $opensslError = $ex->getMessage();
                }
            }

            // Teste de Round-trip (Criação e Leitura) no ambiente real
            $roundTripSuccess = false;
            $roundTripError = null;
            try {
                $testId = (string) \Illuminate\Support\Str::uuid();
                $cryptoService = app(\App\Services\CryptoService::class);
                $testDek = $cryptoService->getOrCreateDek($testId);
                $testEncrypted = $cryptoService->encrypt("12345678900", $testDek);
                $testDekRetrieved = $cryptoService->getDek($testId);
                $testDecrypted = $cryptoService->decrypt($testEncrypted, $testDekRetrieved);
                $roundTripSuccess = ($testDecrypted === "12345678900");
                
                // Cleanup do teste
                DB::table('record_encryption_keys')->where('record_id', $testId)->delete();
            } catch (\Exception $e) {
                $roundTripError = $e->getMessage();
            }

            return response()->json([
                'patient_id' => $patient->id,
                'raw_cpf' => $patient->cpf,
                'raw_cns' => $patient->cns,
                'cpf_token' => $patient->cpf_token ?? 'null',
                'cns_token' => $patient->cns_token ?? 'null',
                'dek_record' => $dekRecord ? 'FOUND' : 'NOT FOUND',
                'raw_dek' => $dekRecord ? $dekRecord->encrypted_dek : null,
                'debug_decrypted_cpf' => $debugDecryptedCpf,
                'openssl_error' => $opensslError,
                'lengths' => [
                    'dek' => isset($dek) ? strlen($dek) : null,
                    'decoded' => isset($decoded) ? strlen($decoded) : null,
                    'iv' => isset($iv) ? strlen($iv) : null,
                    'tag' => isset($tag) ? strlen($tag) : null,
                    'ciphertext' => isset($ciphertext) ? strlen($ciphertext) : null,
                ],
                'round_trip_success' => $roundTripSuccess,
                'round_trip_error' => $roundTripError,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }
}

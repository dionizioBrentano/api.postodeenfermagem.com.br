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
                    // Pega o DEK raw usando o encrypter
                    $kekEncrypter = new \Illuminate\Encryption\Encrypter(
                        env('APP_KEK') ?: \Illuminate\Support\Facades\Config::get('app.key'),
                        \Illuminate\Support\Facades\Config::get('app.cipher', 'AES-256-CBC')
                    );
                    $dek = $kekEncrypter->decrypt($dekRecord->encrypted_dek);
                    
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
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }
}

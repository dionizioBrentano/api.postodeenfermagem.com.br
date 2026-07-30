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

            return response()->json([
                'patient_id' => $patient->id,
                'raw_cpf' => $patient->cpf,
                'raw_cns' => $patient->cns,
                'cpf_token' => $patient->cpf_token ?? 'null',
                'cns_token' => $patient->cns_token ?? 'null',
                'dek_record' => $dekRecord ? 'FOUND' : 'NOT FOUND',
                'raw_dek' => $dekRecord ? $dekRecord->encrypted_dek : null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }
}

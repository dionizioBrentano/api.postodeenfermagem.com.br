<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;

class PatientController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        // Recupera pacientes do tenant do usuario logado.
        // O Cast EncryptedWithDek vai descriptografar automaticamente o CPF e CNS na hora de serializar para JSON!
        $patients = Patient::where('tenant_id', $tenantId)->get();

        return response()->json($patients);
    }
}

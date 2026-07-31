<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\Patient;
use App\Services\AuditService;
use App\Http\Requests\ConsentRequest;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function index(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $consents = Consent::where('patient_id', $patient->id)->orderBy('created_at', 'desc')->get();

        return response()->json($consents);
    }

    public function store(ConsentRequest $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        // Se houver um consentimento válido atual, vamos revogá-lo
        $validConsent = $patient->validConsent;
        if ($validConsent) {
            $validConsent->revoke();
            AuditService::log('revoked_for_new', $validConsent);
        }

        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['status'] = 'valid';

        $consent = Consent::create($data);

        AuditService::log('created', $consent);

        return response()->json($consent, 201);
    }

    public function show(string $patientId, string $id)
    {
        $consent = Consent::where('patient_id', $patientId)->findOrFail($id);

        AuditService::log('accessed', $consent);

        return response()->json($consent);
    }

    public function revoke(string $patientId, string $id)
    {
        $consent = Consent::where('patient_id', $patientId)->findOrFail($id);
        
        if ($consent->status === 'valid') {
            $consent->revoke();
            AuditService::log('revoked', $consent);
        }

        return response()->json($consent);
    }
}

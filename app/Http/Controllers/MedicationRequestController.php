<?php

namespace App\Http\Controllers;

use App\Models\MedicationRequest;
use App\Models\Patient;
use App\Models\Encounter;
use App\Http\Requests\StoreMedicationRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MedicationRequestController extends Controller
{
    public function index(Request $request, string $patientId, string $encounterId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $medications = MedicationRequest::where('encounter_id', $encounter->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($medications as $medication) {
            AuditService::log('accessed', $medication);
        }

        return response()->json($medications);
    }

    public function store(StoreMedicationRequest $request, string $patientId, string $encounterId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['encounter_id'] = $encounter->id;
        $data['user_id'] = $request->user()->id;

        $medication = MedicationRequest::create($data);
        AuditService::log('created', $medication);

        return response()->json($medication, 201);
    }

    public function show(string $patientId, string $encounterId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);
        $medication = MedicationRequest::where('encounter_id', $encounter->id)->findOrFail($id);

        AuditService::log('accessed', $medication);

        return response()->json($medication);
    }

    public function update(StoreMedicationRequest $request, string $patientId, string $encounterId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);
        $medication = MedicationRequest::where('encounter_id', $encounter->id)->findOrFail($id);

        $medication->update($request->validated());
        AuditService::log('updated', $medication);

        return response()->json($medication);
    }
}

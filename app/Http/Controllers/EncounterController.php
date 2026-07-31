<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\Patient;
use App\Http\Requests\EncounterRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EncounterController extends Controller
{
    public function index(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);

        $encounters = Encounter::where('patient_id', $patient->id)
            ->orderBy('start_time', 'desc')
            ->get();

        foreach ($encounters as $encounter) {
            AuditService::log('accessed', $encounter);
        }

        return response()->json($encounters);
    }

    public function store(EncounterRequest $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);

        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['user_id'] = $request->user()->id; // Obrigatório: Profissional logado
        
        if (!isset($data['start_time'])) {
            $data['start_time'] = now();
        }

        $encounter = Encounter::create($data);
        AuditService::log('created', $encounter);

        return response()->json($encounter, 201);
    }

    public function show(string $patientId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);

        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($id);
        AuditService::log('accessed', $encounter);

        return response()->json($encounter);
    }

    public function update(EncounterRequest $request, string $patientId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('accessClinicalData', $patient);

        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($id);
        $encounter->update($request->validated());

        AuditService::log('updated', $encounter);

        return response()->json($encounter);
    }
}

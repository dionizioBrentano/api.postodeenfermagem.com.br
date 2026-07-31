<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use App\Models\Patient;
use App\Models\Encounter;
use App\Http\Requests\ConditionRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConditionController extends Controller
{
    public function index(Request $request, string $patientId, string $encounterId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('viewClinical', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $conditions = Condition::where('encounter_id', $encounter->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($conditions as $condition) {
            AuditService::log('accessed', $condition);
        }

        return response()->json($conditions);
    }

    public function store(ConditionRequest $request, string $patientId, string $encounterId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('createRecord', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['encounter_id'] = $encounter->id;
        $data['user_id'] = $request->user()->id;

        $condition = Condition::create($data);
        AuditService::log('created', $condition);

        return response()->json($condition, 201);
    }

    public function show(string $patientId, string $encounterId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);
        $condition = Condition::where('encounter_id', $encounter->id)->findOrFail($id);
        Gate::authorize('viewRecord', $condition);

        AuditService::log('accessed', $condition);

        return response()->json($condition);
    }

    public function update(ConditionRequest $request, string $patientId, string $encounterId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);
        $condition = Condition::where('encounter_id', $encounter->id)->findOrFail($id);
        Gate::authorize('updateRecord', $condition);

        $condition->update($request->validated());
        AuditService::log('updated', $condition);

        return response()->json($condition);
    }
}

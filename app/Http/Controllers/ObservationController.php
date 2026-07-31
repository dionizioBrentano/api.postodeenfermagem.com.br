<?php

namespace App\Http\Controllers;

use App\Models\Observation;
use App\Models\Patient;
use App\Models\Encounter;
use App\Http\Requests\ObservationRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ObservationController extends Controller
{
    public function index(Request $request, string $patientId, string $encounterId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('viewClinical', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $observations = Observation::where('encounter_id', $encounter->id)
            ->where('status', 'active')
            ->orderBy('recorded_at', 'desc')
            ->get();

        foreach ($observations as $observation) {
            AuditService::log('accessed', $observation);
        }

        return response()->json($observations);
    }

    public function store(ObservationRequest $request, string $patientId, string $encounterId)
    {
        $patient = Patient::findOrFail($patientId);
        Gate::authorize('createRecord', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['encounter_id'] = $encounter->id;
        $data['user_id'] = $request->user()->id; 
        $data['status'] = 'active';
        $data['version'] = 1;

        if (is_array($data['content'])) {
            $data['content'] = json_encode($data['content']);
        }

        $observation = Observation::create($data);
        AuditService::log('created', $observation);

        return response()->json($observation, 201);
    }

    public function show(string $patientId, string $encounterId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);
        $observation = Observation::where('encounter_id', $encounter->id)->findOrFail($id);
        
        Gate::authorize('viewRecord', $observation);

        AuditService::log('accessed', $observation);

        if ($observation->type === 'vital-signs' && is_string($observation->content)) {
            $decoded = json_decode($observation->content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $observation->content = $decoded;
            }
        }

        return response()->json($observation);
    }

    public function update(ObservationRequest $request, string $patientId, string $encounterId, string $id)
    {
        $patient = Patient::findOrFail($patientId);
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);
        $oldObservation = Observation::where('encounter_id', $encounter->id)->findOrFail($id);

        Gate::authorize('updateRecord', $oldObservation);

        if ($oldObservation->status !== 'active') {
            return response()->json(['message' => 'Cannot update a superseded or cancelled observation.'], 422);
        }

        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['encounter_id'] = $encounter->id;
        $data['user_id'] = $request->user()->id; 
        $data['status'] = 'active';
        $data['version'] = $oldObservation->version + 1;
        $data['replaces_id'] = $oldObservation->id;

        if (is_array($data['content'])) {
            $data['content'] = json_encode($data['content']);
        }

        $newObservation = Observation::create($data);
        
        $oldObservation->update(['status' => 'superseded']);

        AuditService::log('superseded', $oldObservation);
        AuditService::log('created', $newObservation);

        return response()->json($newObservation, 201);
    }
}

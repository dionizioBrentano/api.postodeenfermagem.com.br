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
        Gate::authorize('accessClinicalData', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $observations = Observation::where('encounter_id', $encounter->id)
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
        Gate::authorize('accessClinicalData', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);

        $data = $request->validated();
        $data['patient_id'] = $patient->id;
        $data['encounter_id'] = $encounter->id;
        $data['user_id'] = $request->user()->id; // Profissional logado

        // Se o tipo for vital-signs e content for array, vamos transformar em JSON string para salvar.
        // Como o Cast EncryptedWithDek criptografa uma string, json_encode é necessário.
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
        Gate::authorize('accessClinicalData', $patient);
        
        $encounter = Encounter::where('patient_id', $patient->id)->findOrFail($encounterId);
        $observation = Observation::where('encounter_id', $encounter->id)->findOrFail($id);

        AuditService::log('accessed', $observation);

        // Se for JSON valido e tipo vital-signs, podemos tentar decodificar na saída
        if ($observation->type === 'vital-signs' && is_string($observation->content)) {
            $decoded = json_decode($observation->content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $observation->content = $decoded;
            }
        }

        return response()->json($observation);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientUser;
use App\Services\AuditService;
use App\Http\Requests\PatientUserRequest;
use Illuminate\Http\Request;

class PatientUserController extends Controller
{
    public function index(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $links = PatientUser::with('user')
            ->where('patient_id', $patient->id)
            ->get();

        foreach ($links as $link) {
            AuditService::log('accessed', $link);
        }

        return response()->json($links);
    }

    public function store(PatientUserRequest $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $data = $request->validated();
        
        // Atribui o patient_id ao payload
        $data['patient_id'] = $patient->id;
        
        // Como o Tenant já é setado automaticamente pela trait HasTenant ou pode ser explicitado
        $link = PatientUser::create($data);

        return response()->json($link->load('user'), 201);
    }

    public function show(string $patientId, string $id)
    {
        $link = PatientUser::with('user')
            ->where('patient_id', $patientId)
            ->findOrFail($id);

        AuditService::log('accessed', $link);

        return response()->json($link);
    }

    public function update(PatientUserRequest $request, string $patientId, string $id)
    {
        $link = PatientUser::where('patient_id', $patientId)->findOrFail($id);
        
        $data = $request->validated();
        $link->update($data);

        AuditService::log('updated', $link);

        return response()->json($link->load('user'));
    }

    public function destroy(string $patientId, string $id)
    {
        $link = PatientUser::where('patient_id', $patientId)->findOrFail($id);
        $link->delete();

        AuditService::log('deleted', $link);

        return response()->json(null, 204);
    }
}

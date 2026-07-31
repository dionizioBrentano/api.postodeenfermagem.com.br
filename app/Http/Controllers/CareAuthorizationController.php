<?php

namespace App\Http\Controllers;

use App\Models\CareAuthorization;
use App\Models\Patient;
use App\Models\User;
use App\Services\CareAuthorizationService;
use Illuminate\Http\Request;

class CareAuthorizationController extends Controller
{
    public function index(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $authorizations = CareAuthorization::where('patient_id', $patient->id)
            ->with(['grantor', 'grantee'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($authorizations);
    }

    public function show(string $patientId, string $id)
    {
        $authorization = CareAuthorization::where('patient_id', $patientId)->findOrFail($id);
        return response()->json($authorization);
    }

    public function store(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $data = $request->validate([
            'grantee_user_id' => 'required|uuid',
            'scope' => 'array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'reason' => 'nullable|string',
            'parent_authorization_id' => 'nullable|uuid',
        ]);

        $grantee = User::findOrFail($data['grantee_user_id']);
        $grantor = $request->user();
        
        $scopes = $data['scope'] ?? ['clinical:read', 'clinical:write'];
        $parentAuth = isset($data['parent_authorization_id']) 
            ? CareAuthorization::findOrFail($data['parent_authorization_id']) 
            : null;
            
        $startsAt = isset($data['starts_at']) ? new \DateTime($data['starts_at']) : null;
        $endsAt = isset($data['ends_at']) ? new \DateTime($data['ends_at']) : null;

        $authorization = CareAuthorizationService::grant(
            $patient,
            $grantor,
            $grantee,
            $scopes,
            $parentAuth,
            null,
            $startsAt,
            $endsAt,
            $data['reason'] ?? null
        );

        return response()->json($authorization, 201);
    }

    public function revoke(Request $request, string $patientId, string $id)
    {
        $authorization = CareAuthorization::where('patient_id', $patientId)->findOrFail($id);
        
        $reason = $request->input('reason');
        
        CareAuthorizationService::revoke($authorization, $request->user(), $reason);
        
        return response()->json($authorization->fresh());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CareAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ConsentController extends Controller
{
    public function index(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $consents = Consent::where('patient_id', $patient->id)->orderBy('created_at', 'desc')->get();

        return response()->json($consents);
    }

    public function store(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $data = $request->validate([
            'context' => 'nullable|string',
            'purposes' => 'nullable|array',
            'data_categories' => 'nullable|array',
            'valid_until' => 'nullable|date',
            'consent_text_version' => 'nullable|string',
            'consent_text_hash' => 'nullable|string',
            'authenticated_with' => 'nullable|string',
            'accepted_by_user_id' => 'nullable|uuid',
            'subject_user_id' => 'nullable|uuid',
            'professional_user_id' => 'nullable|uuid',
            'appointment_id' => 'nullable|uuid',
            'requires_dual_guardian' => 'nullable|boolean',
            'guardian_slot' => 'nullable|integer',
            'paired_consent_id' => 'nullable|uuid',
            'metadata' => 'nullable|array',
        ]);

        $data['patient_id'] = $patient->id;
        $data['status'] = 'pending'; // Start as pending until accepted

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

    public function accept(Request $request, string $patientId, string $id)
    {
        $consent = Consent::where('patient_id', $patientId)->findOrFail($id);
        $patient = $consent->patient;

        if ($consent->status !== 'pending') {
            return response()->json(['message' => 'Consent is not pending.'], 422);
        }

        // Validate password if required
        if ($consent->authenticated_with === 'password') {
            $request->validate(['password' => 'required|string']);
            
            $acceptedByUser = User::find($consent->accepted_by_user_id);
            if (!$acceptedByUser || !Hash::check($request->password, $acceptedByUser->password)) {
                return response()->json(['message' => 'Invalid password.'], 401);
            }
        }

        // Update status to valid
        $consent->update([
            'status' => 'valid',
            'accepted_at' => now(),
        ]);
        
        AuditService::log('accepted', $consent);

        // Handle Care Authorization linking
        if (in_array($consent->context, ['appointment_care', 'institutional_care'])) {
            $canGrant = true;

            if ($consent->requires_dual_guardian) {
                // Check if the paired consent is also valid
                if ($consent->paired_consent_id) {
                    $paired = Consent::find($consent->paired_consent_id);
                    if (!$paired || $paired->status !== 'valid') {
                        $canGrant = false;
                    }
                } else {
                    // Check if there is another consent pointing to this one
                    $paired = Consent::where('paired_consent_id', $consent->id)->where('status', 'valid')->first();
                    if (!$paired) {
                        $canGrant = false;
                    }
                }
            }

            if ($canGrant && $consent->professional_user_id) {
                $grantee = User::find($consent->professional_user_id);
                if ($grantee) {
                    CareAuthorizationService::grantFromConsent($consent, $grantee);
                    AuditService::log('consent.appointment_care.linked_authorization', $consent);
                }
            }
        }

        return response()->json($consent);
    }

    public function deny(Request $request, string $patientId, string $id)
    {
        $consent = Consent::where('patient_id', $patientId)->findOrFail($id);
        
        if ($consent->status !== 'pending') {
            return response()->json(['message' => 'Consent is not pending.'], 422);
        }

        $consent->update([
            'status' => 'denied',
            'denied_at' => now(),
        ]);

        AuditService::log('denied', $consent);

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

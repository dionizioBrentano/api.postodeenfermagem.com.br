<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Models\PatientUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can access the clinical data (encounters, observations, etc.) of the patient.
     * Checks if:
     * 1. Patient belongs to the same tenant as the user (already handled by global scope usually, but good to assert).
     * 2. The user has an ACTIVE link with the patient in the patient_user table.
     */
    public function accessClinicalData(User $user, Patient $patient): bool
    {
        if ($user->tenant_id !== $patient->tenant_id) {
            return false;
        }

        $activeLink = PatientUser::where('user_id', $user->id)
            ->where('patient_id', $patient->id)
            ->where('ativo', true)
            ->first();

        return $activeLink !== null;
    }
}

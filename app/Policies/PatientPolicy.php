<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Models\PatientUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientPolicy
{
    use HandlesAuthorization;

    public function accessClinicalData(User $user, Patient $patient): bool
    {
        return \App\Services\CareAuthorizationService::userCanAccessPatient($user, $patient, 'clinical:read');
    }

    public function viewPatient(User $user, Patient $patient): bool
    {
        return \App\Services\CareAuthorizationService::userCanAccessPatient($user, $patient, 'clinical:read');
    }

    public function viewClinical(User $user, Patient $patient): bool
    {
        return \App\Services\CareAuthorizationService::userCanAccessPatient($user, $patient, 'clinical:read');
    }

    public function writeClinical(User $user, Patient $patient): bool
    {
        return \App\Services\CareAuthorizationService::userCanAccessPatient($user, $patient, 'clinical:write');
    }

    public function createRecord(User $user, Patient $patient): bool
    {
        return \App\Services\CareAuthorizationService::userCanAccessPatient($user, $patient, 'clinical:write');
    }

    public function viewRecord(User $user, $record): bool
    {
        return \App\Services\CareAuthorizationService::userCanAccessRecord($user, $record, 'clinical:read');
    }

    public function updateRecord(User $user, $record): bool
    {
        return \App\Services\CareAuthorizationService::userCanAccessRecord($user, $record, 'clinical:write');
    }
}

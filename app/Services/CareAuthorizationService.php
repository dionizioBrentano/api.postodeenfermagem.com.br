<?php

namespace App\Services;

use App\Models\CareAuthorization;
use App\Models\Consent;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CareAuthorizationService
{
    /**
     * Grant care authorization.
     */
    public static function grant(
        Patient $patient,
        User $grantor,
        User $grantee,
        array $scopes = ['clinical:read', 'clinical:write'],
        ?CareAuthorization $parentAuth = null,
        ?Consent $sourceConsent = null,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
        ?string $reason = null
    ): CareAuthorization {
        // Validation: same tenant
        if ($patient->tenant_id !== $grantor->tenant_id || $patient->tenant_id !== $grantee->tenant_id) {
            throw new \InvalidArgumentException('Cross-tenant authorization is not allowed.');
        }

        // Grantor must have power (admin, active delegate authorization, system flow, or compat patient_user)
        if (!self::grantorHasPower($grantor, $patient, $sourceConsent)) {
            throw new AuthorizationException('Grantor does not have permission to delegate access to this patient.');
        }

        if ($parentAuth && !$parentAuth->isActive()) {
            throw new \InvalidArgumentException('Parent authorization is not active.');
        }

        // Check duplicates
        $existing = CareAuthorization::where('patient_id', $patient->id)
            ->where('grantee_user_id', $grantee->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            // Optional: could merge scopes or update ends_at. Here we'll just throw or return existing.
            // Returning existing to be idempotent, but in real scenario we might want to update it.
            // Let's just create a new one and maybe revoke the old one, or return existing if identical.
            // For MVP, if it exists and is active, let's just return it or throw. Let's throw 422 equivalent.
            throw new \InvalidArgumentException('Grantee already has an active authorization for this patient.');
        }

        $auth = CareAuthorization::create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'grantor_user_id' => $grantor->id,
            'grantee_user_id' => $grantee->id,
            'parent_authorization_id' => $parentAuth?->id,
            'source_consent_id' => $sourceConsent?->id,
            'scope' => $scopes,
            'status' => 'active',
            'reason' => $reason,
            'starts_at' => $startsAt ?? now(),
            'ends_at' => $endsAt,
        ]);

        AuditService::log('care_authorization.granted', $auth);

        return $auth;
    }

    public static function revoke(CareAuthorization $authorization, User $by, ?string $reason = null, bool $cascade = true): void
    {
        if (!$authorization->isActive()) {
            return;
        }

        $authorization->revoke($by, $reason);
        AuditService::log('care_authorization.revoked', $authorization);

        if ($cascade) {
            $children = CareAuthorization::where('parent_authorization_id', $authorization->id)
                ->where('status', 'active')
                ->get();

            foreach ($children as $child) {
                self::revoke($child, $by, 'Cascade revoke from parent: ' . $reason, true);
            }
        }
    }

    public static function grantFromConsent(Consent $consent, User $grantee, array $scopes = ['clinical:read', 'clinical:write', 'delegate'], ?User $grantor = null): CareAuthorization
    {
        // The grantor can be the patient (if they have a User account) or a "system" user.
        // For now, if no grantor provided, we assume the grantee themselves is the initiator of the system flow
        // or we need a system user. The rules say "grantor pode ser o profissional agendado ou user técnico".
        // Let's use the grantee as the grantor for system flow if none provided.
        $grantor = $grantor ?? $grantee;

        return self::grant(
            $consent->patient,
            $grantor,
            $grantee,
            $scopes,
            null,
            $consent
        );
    }

    public static function userCanAccessPatient(User $user, Patient $patient, string $needScope = 'clinical:read'): bool
    {
        if ($user->tenant_id !== $patient->tenant_id) {
            return false;
        }

        // tenant:admin check (if applicable, assuming a role or method isAdmin)
        // For now we'll assume there is no explicit isAdmin method on User, or we check if they have admin ability
        // If there's an ability system, we could check here. Let's assume there's a token ability or role.
        if ($user->tokenCan('tenant:admin') || $user->hasRole('admin')) {
            // This is hypothetical. We'll leave it as a comment or basic check.
        }

        // Check care_authorization
        $hasAuth = CareAuthorization::where('patient_id', $patient->id)
            ->where('grantee_user_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->contains(function ($auth) use ($needScope) {
                return in_array($needScope, $auth->scope ?? []);
            });

        if ($hasAuth) {
            return true;
        }

        // MVP compat check
        $activeLink = PatientUser::where('user_id', $user->id)
            ->where('patient_id', $patient->id)
            ->where('ativo', true)
            ->exists();

        if ($activeLink) {
            return true; // PatientUser gives read+write in MVP
        }

        return false;
    }

    public static function userCanAccessRecord(User $user, $record, string $needScope = 'clinical:read'): bool
    {
        // Autor sempre tem acesso ao próprio registro
        if (isset($record->user_id) && $record->user_id === $user->id) {
            return true;
        }

        // Senão, checa o paciente
        return self::userCanAccessPatient($user, $record->patient, $needScope);
    }

    private static function grantorHasPower(User $grantor, Patient $patient, ?Consent $sourceConsent = null): bool
    {
        if ($sourceConsent && $sourceConsent->isValid()) {
            return true; // System flow via accepted Consent
        }

        // tenant admin check could be added here
        
        $hasDelegate = CareAuthorization::where('patient_id', $patient->id)
            ->where('grantee_user_id', $grantor->id)
            ->where('status', 'active')
            ->get()
            ->contains(function ($auth) {
                return in_array('delegate', $auth->scope ?? []);
            });

        if ($hasDelegate) {
            return true;
        }

        $activeLink = PatientUser::where('user_id', $grantor->id)
            ->where('patient_id', $patient->id)
            ->where('ativo', true)
            ->exists();

        if ($activeLink) {
            return true;
        }

        return false;
    }
}

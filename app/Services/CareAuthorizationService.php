<?php

namespace App\Services;

use App\Models\CareAuthorization;
use App\Models\Consent;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CareAuthorizationService
{
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
        if ($patient->tenant_id !== $grantor->tenant_id || $patient->tenant_id !== $grantee->tenant_id) {
            throw new \InvalidArgumentException('Cross-tenant authorization is not allowed.');
        }

        if (! self::grantorHasPower($grantor, $patient, $sourceConsent)) {
            throw new AuthorizationException('Grantor does not have permission to delegate access to this patient.');
        }

        if ($parentAuth && ! $parentAuth->isActive()) {
            throw new \InvalidArgumentException('Parent authorization is not active.');
        }

        $existing = CareAuthorization::where('patient_id', $patient->id)
            ->where('grantee_user_id', $grantee->id)
            ->where('status', 'active')
            ->first();

        // Idempotente: accept/seed não devem gerar 500 se já existe authorization
        if ($existing) {
            if ($sourceConsent && ! $existing->source_consent_id) {
                $existing->update(['source_consent_id' => $sourceConsent->id]);
            }

            return $existing;
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
        if (! $authorization->isActive()) {
            return;
        }

        $authorization->revoke($by, $reason);
        AuditService::log('care_authorization.revoked', $authorization);

        if ($cascade) {
            $children = CareAuthorization::where('parent_authorization_id', $authorization->id)
                ->where('status', 'active')
                ->get();

            foreach ($children as $child) {
                self::revoke($child, $by, 'Cascade revoke from parent: '.$reason, true);
            }
        }
    }

    public static function grantFromConsent(
        Consent $consent,
        User $grantee,
        array $scopes = ['clinical:read', 'clinical:write', 'delegate'],
        ?User $grantor = null
    ): CareAuthorization {
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

        if (self::isTenantAdmin($user)) {
            return true;
        }

        $authorizations = CareAuthorization::where('patient_id', $patient->id)
            ->where('grantee_user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($authorizations as $auth) {
            if (! $auth->isActive()) {
                continue;
            }

            $scopes = is_array($auth->scope) ? $auth->scope : [];

            if (in_array($needScope, $scopes, true)) {
                return true;
            }
            if ($needScope === 'clinical:read' && in_array('clinical:write', $scopes, true)) {
                return true;
            }
        }

        return PatientUser::where('user_id', $user->id)
            ->where('patient_id', $patient->id)
            ->where('ativo', true)
            ->exists();
    }

    public static function userCanAccessRecord(User $user, $record, string $needScope = 'clinical:read'): bool
    {
        if (isset($record->user_id) && $record->user_id === $user->id) {
            return true;
        }

        $patient = $record->patient ?? null;
        if (! $patient instanceof Patient) {
            return false;
        }

        return self::userCanAccessPatient($user, $patient, $needScope);
    }

    private static function isTenantAdmin(User $user): bool
    {
        try {
            if (method_exists($user, 'tokenCan') && $user->tokenCan('tenant:admin')) {
                return true;
            }
        } catch (\Throwable $e) {
            // token ausente
        }

        return isset($user->user_type) && $user->user_type === 'admin' && $user->tenant_id !== null;
    }

    private static function grantorHasPower(User $grantor, Patient $patient, ?Consent $sourceConsent = null): bool
    {
        if ($sourceConsent && $sourceConsent->status === 'valid') {
            return true;
        }

        if (self::isTenantAdmin($grantor)) {
            return true;
        }

        $hasDelegate = CareAuthorization::where('patient_id', $patient->id)
            ->where('grantee_user_id', $grantor->id)
            ->where('status', 'active')
            ->get()
            ->contains(function ($auth) {
                if (! $auth->isActive()) {
                    return false;
                }
                $scopes = is_array($auth->scope) ? $auth->scope : [];

                return in_array('delegate', $scopes, true);
            });

        if ($hasDelegate) {
            return true;
        }

        return PatientUser::where('user_id', $grantor->id)
            ->where('patient_id', $patient->id)
            ->where('ativo', true)
            ->exists();
    }
}

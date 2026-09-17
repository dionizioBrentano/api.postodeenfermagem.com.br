<?php

namespace App\Models;

use App\Casts\EncryptedWithDek;
use App\Models\Tenant;
use App\Services\TokenizationService;
use App\Traits\HasEncryptedFields;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasTenant, HasUuids, SoftDeletes, HasEncryptedFields;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'user_type',
        'council_type',
        'council_number',
        'mfa_secret',
        'mfa_enabled',
        'phone',
        'cpf',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'council_number_token',
        'cpf_token',
        'phone_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_enabled' => 'boolean',
            'mfa_secret' => EncryptedWithDek::class,
            // Numero do conselho (CRM/COREN/etc.) nunca fica em texto puro.
            'council_number' => EncryptedWithDek::class.':council_number_token',
            'cpf' => EncryptedWithDek::class.':cpf_token',
            'phone' => EncryptedWithDek::class.':phone_token',
        ];
    }

    /**
     * Localiza um usuário pelo número do conselho profissional, via blind index.
     */
    public static function findByCouncilNumber(string $councilNumber): ?self
    {
        $token = app(TokenizationService::class)->tokenize($councilNumber);

        return static::where('council_number_token', $token)->first();
    }

    /**
     * Localiza um usuário pelo CPF, via blind index no tenant atual.
     */
    public static function findByCpf(string $cpf): ?self
    {
        $tokenService = app(TokenizationService::class);
        $tenantId = app()->has('tenant') ? (app('tenant') instanceof Tenant ? app('tenant')->id : app('tenant')) : null;

        $token = $tokenService->tokenize($cpf);
        $query = static::where('cpf_token', $token);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $user = $query->first();
        if ($user) {
            return $user;
        }

        $digits = preg_replace('/[^0-9]/', '', $cpf);
        if ($digits !== '' && $digits !== $cpf) {
            $token = $tokenService->tokenize($digits);
            $query = static::where('cpf_token', $token);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            return $query->first();
        }

        return null;
    }

    /**
     * Localiza um usuário pelo telefone, via blind index no tenant atual.
     */
    public static function findByPhone(string $phone): ?self
    {
        $tokenService = app(TokenizationService::class);
        $tenantId = app()->has('tenant') ? (app('tenant') instanceof Tenant ? app('tenant')->id : app('tenant')) : null;

        // 1. Tenta direto como passado
        $token = $tokenService->tokenize($phone);
        $query = static::where('phone_token', $token);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $user = $query->first();
        if ($user) {
            return $user;
        }

        // 2. Tenta apenas dígitos
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits !== '' && $digits !== $phone) {
            $token = $tokenService->tokenize($digits);
            $query = static::where('phone_token', $token);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            $user = $query->first();
            if ($user) {
                return $user;
            }
        }

        // 3. Se dígitos começam com DDI 55 e tem 12 ou 13 dígitos, tenta sem 55
        if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
            $withoutCountry = substr($digits, 2);
            $token = $tokenService->tokenize($withoutCountry);
            $query = static::where('phone_token', $token);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            $user = $query->first();
            if ($user) {
                return $user;
            }
        }

        // 4. Se tem 10 ou 11 dígitos, tenta com DDI 55
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $withCountry = '55' . $digits;
            $token = $tokenService->tokenize($withCountry);
            $query = static::where('phone_token', $token);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            $user = $query->first();
            if ($user) {
                return $user;
            }

            $token = $tokenService->tokenize('+' . $withCountry);
            $query = static::where('phone_token', $token);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            return $query->first();
        }

        return null;
    }

    public function encounters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function observations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function conditions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Condition::class);
    }

    public function medicationRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MedicationRequest::class);
    }

    public function identities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }
}

<?php

namespace App\Models;

use App\Casts\EncryptedWithDek;
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
            // Numero do conselho (CRM/COREN/etc.) nunca fica em texto puro.
            'council_number' => EncryptedWithDek::class.':council_number_token',
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
}

<?php

namespace App\Models;

use App\Casts\EncryptedWithDek;
use App\Services\TokenizationService;
use App\Traits\Auditable;
use App\Traits\HasEncryptedFields;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, HasEncryptedFields, Auditable;

    protected $fillable = [
        'tenant_id',
        'name',
        'cpf',
        'cns',
    ];

    protected $hidden = [
        'cpf_token',
        'cns_token',
    ];

    protected function casts(): array
    {
        return [
            // Uma única DEK por paciente (ver HasEncryptedFields) cifra tanto
            // o cpf quanto o cns; cada um mantém seu próprio blind index.
            'cpf' => EncryptedWithDek::class.':cpf_token',
            'cns' => EncryptedWithDek::class.':cns_token',
        ];
    }

    /**
     * Localiza um paciente pelo CPF sem precisar descriptografar a base
     * inteira (busca pelo blind index / token).
     */
    public static function findByCpf(string $cpf): ?self
    {
        $token = app(TokenizationService::class)->tokenize($cpf);

        return static::where('cpf_token', $token)->first();
    }

    /**
     * Localiza um paciente pelo CNS (mesma lógica de blind index).
     */
    public static function findByCns(string $cns): ?self
    {
        $token = app(TokenizationService::class)->tokenize($cns);

        return static::where('cns_token', $token)->first();
    }
}

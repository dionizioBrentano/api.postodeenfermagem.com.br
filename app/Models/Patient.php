<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasTenant;
use App\Traits\Auditable;
use App\Casts\EncryptedWithDek;

class Patient extends Model
{
    use HasUuids, SoftDeletes, HasTenant, Auditable;

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
            // Passamos o segundo parametro para gerar o Blind Index automaticamente!
            'cpf' => EncryptedWithDek::class.':id,cpf_token',
            'cns' => EncryptedWithDek::class.':id,cns_token',
        ];
    }
}

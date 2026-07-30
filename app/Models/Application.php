<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasTenant;

class Application extends Authenticatable
{
    use HasUuids, HasApiTokens, HasTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'client_id',
        'client_secret',
        'scopes',
        'status',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
        ];
    }
}

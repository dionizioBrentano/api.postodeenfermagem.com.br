<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePoint extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'cep',
        'latitude',
        'longitude',
        'coverage_km',
        'quality_score',
        'active',
    ];

    protected $attributes = [
        'coverage_km' => 10.0,
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'coverage_km' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

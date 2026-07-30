<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootHasTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('tenant')) {
                $tenant = app('tenant');
                if ($tenant instanceof Tenant) {
                    $builder->where('tenant_id', $tenant->id);
                }
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && app()->has('tenant')) {
                $tenant = app('tenant');
                if ($tenant instanceof Tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });
    }

    /**
     * Relacionamento com o Tenant.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

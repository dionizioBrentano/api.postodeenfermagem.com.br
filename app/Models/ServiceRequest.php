<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, Auditable;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_ACCEPTED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    public const SLOT_WINDOW_MANHA = 'manha';
    public const SLOT_WINDOW_TARDE = 'tarde';
    public const SLOT_WINDOW_NOITE = 'noite';

    public const SLOT_WINDOWS = [
        self::SLOT_WINDOW_MANHA,
        self::SLOT_WINDOW_TARDE,
        self::SLOT_WINDOW_NOITE,
    ];

    protected $fillable = [
        'tenant_id',
        'client_user_id',
        'offering_id',
        'service_point_id',
        'procedure_id',
        'cep_servico',
        'latitude',
        'longitude',
        'slot_date',
        'slot_window',
        'status',
        'notes_cliente',
    ];

    protected $attributes = [
        'status' => self::STATUS_REQUESTED,
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date:Y-m-d',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(ServiceReview::class);
    }
}

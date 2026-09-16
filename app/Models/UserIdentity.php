<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'email_at_provider',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RecordEncryptionKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'record_id',
        'encrypted_dek',
    ];
}

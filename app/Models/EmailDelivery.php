<?php

namespace App\Models;

use Database\Factories\EmailDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'user_id', 'notification_type', 'status', 'attempts', 'provider_id', 'failure_category', 'expires_at', 'next_attempt_at', 'request_payload'])]
#[Hidden(['request_payload'])]
class EmailDelivery extends Model
{
    /** @use HasFactory<EmailDeliveryFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'request_payload' => 'encrypted',
        ];
    }
}

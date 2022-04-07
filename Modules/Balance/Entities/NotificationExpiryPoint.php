<?php

namespace Modules\Balance\Entities;

use Illuminate\Database\Eloquent\Model;

class NotificationExpiryPoint extends Model
{
    protected $primaryKey = 'id_notification_expiry_point';
    protected $fillable = [
        'id_user',
        'total_point',
        'expired_date'
    ];
}

<?php

namespace Modules\Balance\Entities;

use Illuminate\Database\Eloquent\Model;

class NotificationExpiryPointSentUser extends Model
{
    protected $primaryKey = 'id_notification_expiry_point_sent_user';
    protected $fillable = [
        'id_notification_expiry_point_sent',
        'id_user',
        'total_expired_point',
        'expired_date'
    ];
}

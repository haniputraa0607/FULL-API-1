<?php

namespace Modules\Balance\Entities;

use Illuminate\Database\Eloquent\Model;

class NotificationExpiryPointSent extends Model
{
    protected $primaryKey = 'id_notification_expiry_point_sent';

    protected $fillable = [
        'total_customer',
        'notification_expiry_point_date_sent',
        'email_count_sent',
        'sms_count_sent',
        'push_count_sent',
        'inbox_count_sent',
        'whatsapp_count_sent'
    ];
}

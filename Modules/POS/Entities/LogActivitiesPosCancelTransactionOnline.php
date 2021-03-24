<?php

namespace Modules\POS\Entities;

use Illuminate\Database\Eloquent\Model;

class LogActivitiesPosCancelTransactionOnline extends \App\Http\Models\BaseLog
{
    protected $primaryKey = 'id_log_activities_pos_cancel_transaction_online';

    protected $connection = 'mysql2';
	
    protected $fillable = [
        'url', 
        'outlet_code',  
        'user', 
        'request', 
        'response_status', 
        'response', 
        'ip', 
        'useragent', 
        'created_at', 
        'updated_at'
    ];
}

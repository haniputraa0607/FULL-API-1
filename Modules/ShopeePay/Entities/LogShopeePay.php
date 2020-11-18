<?php

namespace Modules\ShopeePay\Entities;

use Illuminate\Database\Eloquent\Model;

class LogShopeePay extends \App\Http\Models\BaseLog
{
	public $primaryKey = 'id_log_shopee_pay';
    protected $fillable = [
    	'type',
    	'id_reference',
    	'request',
        'request_url',
    	'request_header',
    	'response',
        'response_header',
    	'response_status_code'
    ];

}

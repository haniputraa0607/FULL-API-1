<?php

namespace App\Http\Models;

class LogApiGosend extends \App\Http\Models\BaseLog
{
	public $primaryKey = 'id_log_api_gosend';
	protected $connection = 'mysql2';
    protected $fillable = [
    	'type',
    	'id_reference',
    	'request_url',
    	'request_method',
        'request_header',
        'request_parameter',
    	'response_body',
        'response_header',
    	'response_code'
    ];
}

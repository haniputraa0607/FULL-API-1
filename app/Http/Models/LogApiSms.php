<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class LogApiSms extends Model
{
	protected $connection = 'mysql2';
    protected $fillable=[
	    'request_body',
	    'request_header',
	    'request_url',
	    'request_method',
	    'response',
	    'more_info',
	    'phone',
	    'user_agent',
    ];
}

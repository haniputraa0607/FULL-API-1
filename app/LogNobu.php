<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LogNobu extends Model
{
    protected $table = 'log_nobu';
    public $primaryKey = 'id_log_nobu';
    
    protected $fillable = [
    	'type',
    	'id_reference',
    	'request',
        'request_url',
    	'request_header',
    	'response',
    	'response_status_code'
    ];

}

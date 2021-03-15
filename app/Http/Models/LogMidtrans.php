<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class LogMidtrans extends Model
{
	public $primaryKey = 'id_log_midtrans';
	// protected $connection = 'mysql2';
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

    public static function __callStatic($method, $parameters)
    {
        if ($method == 'create' && count($parameters) == 1) {
            if (env('DISABLE_LOG_PAYMENT')) {
                return optional(null);
            }

           return parent::create($parameters[0], true);
        } elseif ($method == 'create') {
            return (new static)->$method($parameters[0]);
        }
        return (new static)->$method(...$parameters);
    }
}

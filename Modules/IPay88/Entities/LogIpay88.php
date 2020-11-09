<?php

namespace Modules\IPay88\Entities;

use Illuminate\Database\Eloquent\Model;

class LogIpay88 extends Model
{
	public $primaryKey = 'id_log_ipay88';
    protected $fillable = [
    	'type',
    	'id_reference',
    	'triggers',
    	'request',
        'request_url',
    	'request_header',
    	'response',
    	'response_status_code'
    ];

    public static function __callStatic($method, $parameters)
    {
        if ($method == 'create' && count($parameters) == 1) {
            if (env('DISABLE_LOG')) {
                return optional(null);
            }

           return parent::create($parameters[0], true);
        } elseif ($method == 'create') {
            return (new static)->$method($parameters[0]);
        }
        return (new static)->$method(...$parameters);
    }
}

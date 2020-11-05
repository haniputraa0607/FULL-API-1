<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class LogOvoDeals extends Model
{
	/**
	 * The database name used by the model.
	 *
	 * @var string
	 */
	protected $connection = 'mysql2';
	
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'log_ovo_deals';

    public $primaryKey = 'id_log_ovo_deals';

    /**
     * @var array
     */
    protected $fillable = ['id_log_ovo_deals', 'id_deals_payment_ovo', 'order_id', 'url', 'header', 'request', 'response_status', 'response_code', 'response', 'created_at', 'updated_at'];

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

<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id_log_activity
 * @property integer $id_user
 * @property string $module
 * @property string $action
 * @property string $request
 * @property string $created_at
 * @property string $updated_at
 * @property User $user
 */
class LogActivityLocation extends Model
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
    protected $table = 'log_activity_locations';

    /**
     * @var array
     */
    protected $fillable = [
        'id_log_activity_location', 
        'id_user', 
        'activity', 
        'action', 
        'latitude',
        'longitude', 
        'request_json', 
        'response_josn', 
        'route', 
        'administrative_area_level_5', 
        'administrative_area_level_4', 
        'administrative_area_level_3', 
        'administrative_area_level_2', 
        'administrative_area_level_1',
        'country'
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

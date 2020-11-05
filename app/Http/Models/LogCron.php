<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class LogCron extends Model
{
	protected $primaryKey = 'id_log_cron';
	protected $connection = 'mysql2';

	protected $fillable = [
		'cron',
		'status',
		'start_date',
		'end_date',
		'description'
	];

	public function success($msg = null)
	{
		if (is_array($msg)) {
			$msg = json_encode($msg);
		}
		$this->status = 'success';
		$this->end_date = date('Y-m-d H:i:s');
		$this->description = $msg;
		$this->save();
	}

	public function fail($msg = null)
	{
		if (is_array($msg)) {
			$msg = json_encode($msg);
		}
		$this->status = 'fail';
		$this->end_date = date('Y-m-d H:i:s');
		$this->description = $msg;
		$this->save();
	}

	public function save(array $options = [])
	{
        if (env('DISABLE_LOG')) {
            return optional(null);
        }
        return parent::save($options);
	}

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

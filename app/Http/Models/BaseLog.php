<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use File;
use Storage;
use Spatie\Async\Pool;
use App\Lib\MyHelper;

class BaseLog extends Model
{
    public static $obj = null;

    public static function newObj() {
        if(!get_called_class()::$obj){
            $className = get_called_class();
            get_called_class()::$obj = new $className;
        }
        return get_called_class()::$obj;
    }

    protected static function upload($data) {
        $pool = Pool::create();
        $log_url = env('LOG_POST_URL');
        $pool->add(function () use ($data, $log_url) {
            $ch = curl_init(); 
            curl_setopt($ch,CURLOPT_TIMEOUT,1000);
            curl_setopt($ch, CURLOPT_URL, $log_url);
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_exec($ch);
        });
    }

    public static function __callStatic($method, $parameters)
    {
        if ($method == 'create' && count($parameters) == 1) {
            if (env('DISABLE_LOG')) {
                $table = get_called_class()::newObj()->getTable();
                $data = [
                    'table' => $table,
                    'data' => $parameters
                ];
                self::upload($data);
                return optional(null);
            }

           return parent::create($parameters[0], true);
        } elseif ($method == 'create') {
            return (new static)->$method($parameters[0]);
        }
        return (new static)->$method(...$parameters);
    }
}

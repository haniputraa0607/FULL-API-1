<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use File;
use Storage;
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
        $table = str_replace(' ', '',ucwords(str_replace('_', ' ',$data['table'])));
        $log_url = env('LOG_POST_URL').'/'.$table;
        if (!isset($data['data']['created_at'])) {
            $data['data']['created_at'] = date('Y-m-d H:i:s');
        }
        $data_send = str_replace('\'', "'\\''", json_encode($data['data']));
        $command = "curl --location --request POST '$log_url' --header 'Content-Type: application/json' --data-raw '$data_send' > /dev/null &";
        exec($command);
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

    public function save(array $options = [])
    {
        if (env('DISABLE_LOG')) {
            $table = get_called_class()::newObj()->getTable();
            $data = [
                'table' => $table,
                'data' => $this->attributes
            ];
            self::upload($data);
            return optional(null);
        }
        return parent::save($options);
    }
}

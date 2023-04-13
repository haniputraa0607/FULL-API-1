<?php
namespace App\Lib;

use Image;
use File;
use DB;
use App\Http\Models\Transaction;
use App\Http\Models\DealsUser;
use App\Http\Models\Setting;
use App\Lib\MyHelper;
use App\Http\Models\LogNobu;
use App\Http\Requests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class Nobu {

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    private static function getBaseUrl()
    {
        return env('NOBU_URL', null);
    }

    public static function sendRequest($url = null, $request = null, $company = null, $logType = null, $orderId = null){
        $method = strtolower($method);
        $header = [
            "Content-Type" => "application/json"
        ];
        if($url == '/sales/create_order_poo'){
            $time_out = 2;
        }else{
            $time_out = 1;
        }  
        $response = MyHelper::postWithTimeout(self::getBaseUrl() . $url, null, $request, 0, $header, 65, $fullResponse);

        try {
            $log_response = $response;
            
            $log_api_array = [
                'type'                  => $logType??'',
                'id_reference'          => $orderId??'',
                'request_url'           => self::getBaseUrl() . $url,
                'request'               => json_encode($request),
                'request_header'        => json_encode($header),
                'response'              => json_encode($log_response),
                'response_status_code'  => $fullResponse->getStatusCode()??'',

            ];
            LogNobu::create($log_api_array);
        } catch (\Exception $e) {                    
            \Illuminate\Support\Facades\Log::error('Failed write log to LogNobu: ' . $e->getMessage());
        }        

        return $response;
    }
}
?>
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

    private static function getLogin()
    {
        return 'MAXX';
    }

    private static function getPassword()
    {
        return 'MAXX';
    }

    private static function getMerchantID()
    {
        return '936005030000049084';
    }

    private static function getStoreID()
    {
        return 'ID2020081400327';
    }

    private static function getPosID()
    {
        return 'A01';
    }

    private static function getSecretKey()
    {
        return 'SecretNobuKey';
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

    public static function RequestQRISWithoutTip($request, $logType = null, $orderId = null){
        $data = [
            "login"         => self::getLogin(),
            "password"      => self::getPassword(),
            "merchantID"    => self::getMerchantID(),
            "storeID"       => self::getStoreID(),
            "posID"         => self::getPosID(),
            "transactionNo" => $request['transaction']['transaction_receipt_number'],
            "referenceNo"   => $request['user']['phone'],
            "amount"        => $request['transaction']['transaction_grandtotal'],
            "validTime"     => 60,
            "signature"     => md5(self::getLogin().self::getPassword().self::getMerchantID().self::getStoreID().self::getPosID().$request['transaction']['transaction_receipt_number'].$request['user']['phone'].$request['transaction']['transaction_grandtotal'].'60'.self::getSecretKey(),true)
        ];
        
        return self::sendRequest('http://uatmerchant.nobubank.com:2104/generalNew/Partner/GetQRISSinglePaymentWithoutTip', $data, $logType, $orderId);
    }

    public static function RequestQRIS($request, $logType = null, $orderId = null){
        $data = [
            "login"         => self::getLogin(),
            "password"      => self::getPassword(),
            "merchantID"    => self::getMerchantID(),
            "storeID"       => self::getStoreID(),
            "posID"         => self::getPosID(),
            "transactionNo" => $request['transaction']['transaction_receipt_number'],
            "referenceNo"   => $request['user']['phone'],
            "amount"        => $request['transaction']['transaction_grandtotal'],
            "validTime"     => 60,
            "signature"     => md5(self::getLogin().self::getPassword().self::getMerchantID().self::getStoreID().self::getPosID().$request['transaction']['transaction_receipt_number'].$request['user']['phone'].$request['transaction']['transaction_grandtotal'].'60'.self::getSecretKey(),true)
        ];
        
        return self::sendRequest('http://uatmerchant.nobubank.com:2101/general/Partner/GetQRISSinglePayment', $data, $logType, $orderId);
    }
}
?>
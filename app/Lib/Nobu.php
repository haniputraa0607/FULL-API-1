<?php
namespace App\Lib;

use Image;
use File;
use DB;
use App\Http\Models\Transaction;
use App\Http\Models\DealsUser;
use App\Http\Models\Setting;
use App\Lib\MyHelper;
use App\Http\Models\LogNobu as AppLogNobu;
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
        return env('NOBU_URL', 'http://uatmerchant.nobubank.com');
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

    public static function sendRequest($url = null, $request = null, $logType = null, $orderId = null){
        $header = [
            "Content-Type" => "application/json"
        ];
   
        $data_send = [
            "data" => base64_encode(json_encode($request))
        ];
        $response = MyHelper::postWithTimeout(self::getBaseUrl() . $url, null, $data_send, 0, $header, 65);

        try {
            $log_response = $response;
            
            $log_api_array = [
                'type'                  => $logType??'',
                'id_reference'          => $orderId??'',
                'request_url'           => self::getBaseUrl() . $url,
                'request'               => json_encode($request),
                'request_header'        => json_encode($header),
                'response'              => json_encode($log_response),
                'response_status_code'  => $response['status_code']??'',

            ];
            AppLogNobu::create($log_api_array);
        } catch (\Exception $e) {                    
            \Illuminate\Support\Facades\Log::error('Failed write log to LogNobu: ' . $e->getMessage());
        }        

        return $response;
    }

    public static function RequestQRISWithoutTip($request, $logType = null, $orderId = null){
        $validTime = (int) MyHelper::setting('validity_time_qr_nobu', 'value', 60);
        $validTime = 21600;
        $data = [
            "login"         => self::getLogin(),
            "password"      => self::getPassword(),
            "merchantID"    => self::getMerchantID(),
            "storeID"       => self::getStoreID(),
            "posID"         => self::getPosID(),
            "transactionNo" => $request['transaction']['transaction_receipt_number'],
            "referenceNo"   => $request['user']['phone'],
            "amount"        => $request['transaction']['transaction_grandtotal'],
            "validTime"     => $validTime,
            "signature"     => md5(self::getLogin().self::getPassword().self::getMerchantID().self::getStoreID().self::getPosID().$request['transaction']['transaction_receipt_number'].$request['user']['phone'].$request['transaction']['transaction_grandtotal'].$validTime.self::getSecretKey())
        ];

        return self::sendRequest(':2104/generalNew/Partner/GetQRISSinglePaymentWithoutTip', $data, $logType, $orderId);
    }

    public static function RequestQRIS($request, $logType = null, $orderId = null){
        $validTime = (int) MyHelper::setting('validity_time_qr_nobu', 'value', 60);
        $validTime = 21600;
        $data = [
            "login"         => self::getLogin(),
            "password"      => self::getPassword(),
            "merchantID"    => self::getMerchantID(),
            "storeID"       => self::getStoreID(),
            "posID"         => self::getPosID(),
            "transactionNo" => $request['transaction']['transaction_receipt_number'],
            "referenceNo"   => $request['user']['phone'],
            "amount"        => $request['transaction']['transaction_grandtotal'],
            "validTime"     => $validTime,
            "signature"     => md5(self::getLogin().self::getPassword().self::getMerchantID().self::getStoreID().self::getPosID().$request['transaction']['transaction_receipt_number'].$request['user']['phone'].$request['transaction']['transaction_grandtotal'].$validTime.self::getSecretKey(),true)
        ];
        
        return self::sendRequest(':2101/general/Partner/GetQRISSinglePayment', $data, $logType, $orderId);
    }

    public function InquiryPaymentStatus($request, $logType = null, $orderId){
        $data = [
            "login"         => self::getLogin(),
            "password"      => self::getPassword(),
            "merchantID"    => self::getMerchantID(),
            "storeID"       => self::getStoreID(),
            "posID"         => self::getPosID(),
            "transactionNo" => $request['transaction']['transaction_receipt_number'],
            "signature"     => md5(self::getLogin().self::getPassword().self::getMerchantID().self::getStoreID().self::getPosID().$request['transaction'].self::getSecretKey(),true)
        ];

        return self::sendRequest('/api/Partner/InquiryPayment', $data, $logType, $orderId);

    }

    public function CancelingDynamicQRIS($request, $logType = null, $orderId){
        $data = [
            "login"         => self::getLogin(),
            "password"      => self::getPassword(),
            "merchantID"    => self::getMerchantID(),
            "storeID"       => self::getStoreID(),
            "transactionNo" => $request['transaction']['transaction_receipt_number'],
            "referenceNo"   => $request['user']['phone'],
            "amount"        => $request['transaction']['transaction_grandtotal'],
            "qrisData"      => $request['transaction']['transaction_payment_nobu']['qris_data'],
            "signature"     => md5(self::getLogin().self::getPassword().self::getMerchantID().self::getStoreID().$request['transaction']['transaction_receipt_number'].$request['user']['phone'].$request['transaction']['transaction_grandtotal'].$request['transaction']['transaction_payment_nobu']['qris_data'].self::getSecretKey())
        ];

        return self::sendRequest(':2101/general/Partner/CancelQRIS', $data, $logType, $orderId);

    }
}
?>
<?php
namespace App\Lib;

use DB;

use App\Http\Requests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

use App\Lib\MyHelper;

use App\Http\Models\LogOvo;
use App\Http\Models\LogOvoDeals;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsVoucher;
use App\Http\Models\TransactionPaymentOvo;
use App\Http\Models\DealsPaymentOvo;
use Modules\Outlet\Entities\OutletOvo;
use App\Http\Models\Setting;
use DateTime;
use DateTimeZone;

class Ovo {

    public function __construct() {
        date_default_timezone_set('Asia/Jakarta');
    }

    static function hmac_value($time) {
        if(env('OVO_ENV')=='production'){
            $app_id = env('OVO_PROD_APP_ID');
            $app_key = env('OVO_PROD_APP_KEY');
        }else{
            $app_id = env('OVO_STAGING_APP_ID');
            $app_key = env('OVO_STAGING_APP_KEY');
        }
        return hash_hmac('sha256', $app_id.$time, $app_key);
    }

    public static function checkOutletOvo($id_outlet){
        return OutletOvo::where('id_outlet',$id_outlet)->exists();
    }

    static function PayTransaction($dataTrx, $dataPay, $amount, $env, $type="trx") {
        if($env == 'production'){
            $url = env('OVO_PROD_URL');
            $tid = env('OVO_PROD_TID');
            $mid = env('OVO_PROD_MID');
            $merchantId = env('OVO_PROD_MERCHANT_ID');
            $storeCode = env('OVO_PROD_STORE_CODE');
            $app_id = env('OVO_PROD_APP_ID');
        }else{
            $url    = env('OVO_STAGING_URL');
            $tid = env('OVO_STAGING_TID');
            $mid = env('OVO_STAGING_MID');
            $merchantId = env('OVO_STAGING_MERCHANT_ID');
            $storeCode = env('OVO_STAGING_STORE_CODE');
            $app_id = env('OVO_STAGING_APP_ID');
        }
        if($type != 'deals'){
            $outlet_ovo = OutletOvo::where('id_outlet',$dataTrx['id_outlet']??'')->first();
            if(!$outlet_ovo){
                return false;
            }
            $tid = $outlet_ovo->tid;
            $mid = $outlet_ovo->mid;
            $storeCode = $outlet_ovo->store_code;
        }
        $now = time();

        $data['type'] = "0200";
        $data['processingCode'] = "040000";
        $data['amount'] = (int)$amount;
        // $data['date'] = date('Y-m-d H:i:s.v', strtotime($dataTrx['transaction_date']));
        // $data['date'] = date('Y-m-d H:i:s.v');

        //for millisecond data appears, because if you using a date('Y-m-d H:i:s.v') always return 000
        $datenow = DateTime::createFromFormat('U.u', microtime(true));
        $data['date'] = $datenow->setTimezone(new DateTimeZone('Asia/Jakarta'))->format("Y-m-d H:i:s.v");

        $data['referenceNumber'] = $dataPay['reference_number'];
        $data['tid']        = $tid;
        $data['mid']        = $mid;
        $data['merchantId'] = $merchantId;
        $data['storeCode']  = $storeCode;
        $data['appSource']  = 'POS';
        $data['transactionRequestData'] =[
            'batchNo' => $dataPay['batch_no'],
            'merchantInvoice' => $dataTrx['transaction_receipt_number']??$dataPay['order_id'],
            'phone' => $dataPay['phone'],
        ];

        $header = [
            'hmac' => Self::hmac_value($now),
            'app-id' => $app_id,
            'random' => $now
        ];

        if($type == "deals"){
            //create log request
            $createLog = LogOvoDeals::create([
                'id_deals_payment_ovo' => $dataPay['id_deals_payment_ovo'],
                'order_id' => $dataPay['order_id'],
                'url' => $url,
                'header' => json_encode($header),
                'request' => json_encode($data)
            ]);
            //update push_time
            $updateTime = DealsPaymentOvo::where('id_deals_user', $dataTrx['id_deals_user'])->update(['push_to_pay_at' => date('Y-m-d H:i:s')]);
            $getSettingTimer = Setting::where('key', 'setting_timer_ovo')->first();
            if(isset($getSettingTimer['value'])){
                $pay = MyHelper::postWithTimeout($url, null, $data, 0, $header, (int)$getSettingTimer['value']);
            }else{
                $pay = MyHelper::postWithTimeout($url, null, $data, 0, $header);
            }

            // dd($pay->getStatusCode());
            if(isset($pay['status_code'])){
                $updateLog = $createLog->update([
                    'response_status' => 'success',
                    'response_code' => $pay['status_code'],
                    'response' => json_encode($pay['response'])
                ]);
                // if($pay->getStatusCode() == 200){
                //     $response = json_decode($pay->getBody(), true);
                //     dd($pay->getBody());
                // }

                // $response = json_decode($pay->getBody()->getContents(), true);
                // return [
                //     'status_code' => $pay->getStatusCode(),
                //     'response' => $response
                // ];
                return $pay;

            }

            $updateLog = LogOvoDeals::where('id_log_ovo_deals', $createLog['id_log_ovo_deals'])->update([
                'response_status' => 'fail',
                'response' => json_encode($pay)
            ]);

            return $pay;
        }else{
            //create log request
            $createLog = LogOvo::create([
                'id_transaction_payment_ovo' => $dataPay['id_transaction_payment_ovo'],
                'transaction_receipt_number' => $dataTrx['transaction_receipt_number'],
                'url' => $url,
                'header' => json_encode($header),
                'request' => json_encode($data)
            ]);

            //update push_time
            $updateTime = TransactionPaymentOvo::where('id_transaction', $dataTrx['id_transaction'])->update(['push_to_pay_at' => date('Y-m-d H:i:s')]);
            $getSettingTimer = Setting::where('key', 'setting_timer_ovo')->first();
            if(isset($getSettingTimer['value'])){
                $pay = MyHelper::postWithTimeout($url, null, $data, 0, $header, (int)$getSettingTimer['value']);
            }else{
                $pay = MyHelper::postWithTimeout($url, null, $data, 0, $header);
            }

            // dd($pay->getStatusCode());
            if(isset($pay['status_code'])){
                $updateLog = LogOvo::where('id_log_ovo', $createLog['id_log_ovo'])->update([
                    'response_status' => 'success',
                    'response_code' => $pay['status_code'],
                    'response' => json_encode($pay['response'])
                ]);

                // if($pay->getStatusCode() == 200){
                //     $response = json_decode($pay->getBody(), true);
                //     dd($pay->getBody());
                // }

                // $response = json_decode($pay->getBody()->getContents(), true);
                // return [
                //     'status_code' => $pay->getStatusCode(),
                //     'response' => $response
                // ];
                return $pay;

            }

            $updateLog = LogOvo::where('id_log_ovo', $createLog['id_log_ovo'])->update([
                'response_status' => 'fail',
                'response' => json_encode($pay)
            ]);

            return $pay;
        }

    }

    static function Reversal($dataTrx, $dataPay, $amount, $env,$type='trx') {
        if($env == 'production'){
            $url = env('OVO_PROD_URL');
            $tid = env('OVO_PROD_TID');
            $mid = env('OVO_PROD_MID');
            $merchantId = env('OVO_PROD_MERCHANT_ID');
            $storeCode = env('OVO_PROD_STORE_CODE');
            $app_id = env('OVO_PROD_APP_ID');
        }else{
            $url    = env('OVO_STAGING_URL');
            $tid = env('OVO_STAGING_TID');
            $mid = env('OVO_STAGING_MID');
            $merchantId = env('OVO_STAGING_MERCHANT_ID');
            $storeCode = env('OVO_STAGING_STORE_CODE');
            $app_id = env('OVO_STAGING_APP_ID');
        }

        $data['type'] = "0400";
        $data['processingCode'] = "040000";
        $data['amount'] = $amount;

        // $data['date'] = date('Y-m-d H:i:s.v');

        //for millisecond data appears, because if you using a date('Y-m-d H:i:s.v') always return 000
        $datenow = DateTime::createFromFormat('U.u', microtime(true));
        $data['date'] = $datenow->setTimezone(new DateTimeZone('Asia/Jakarta'))->format("Y-m-d H:i:s.v");

        $data['referenceNumber'] = $dataPay['reference_number'];
        $data['tid']        = $tid;
        $data['mid']        = $mid;
        $data['merchantId'] = $merchantId;
        $data['storeCode']  = $storeCode;
        $data['appSource']  = 'POS';
        $data['transactionRequestData'] =[
            'batchNo' => $dataPay['batch_no'],
            'merchantInvoice' => $dataTrx['transaction_receipt_number']??$dataPay['order_id'],
        ];
        $now = time();

        $header = [
            'hmac' => Self::hmac_value($now),
            'app-id' => $app_id,
            'random' => $now
        ];

        for($i = 1; $i<=3; $i++){

        //create log request
            if($type=='deals'){
                $createLog = LogOvoDeals::create([
                    'id_deals_payment_ovo' => $dataPay['id_deals_payment_ovo'],
                    'order_id' => $dataTrx['order_id'],
                    'url' => $url,
                    'header' => json_encode($header),
                    'request' => json_encode($data)
                ]);
            }else{
                $createLog = LogOvo::create([
                    'id_transaction_payment_ovo' => $dataPay['id_transaction_payment_ovo'],
                    'transaction_receipt_number' => $dataTrx['transaction_receipt_number'],
                    'url' => $url,
                    'header' => json_encode($header),
                    'request' => json_encode($data)
                ]);
            }

            $reversal = MyHelper::postWithTimeout($url, null, $data, 0, $header);

            if(isset($reversal['status_code'])){

                $updateLog = $createLog->update([
                    'response_status' => 'success',
                    'response_code' => $reversal['status_code'],
                    'response' => json_encode($reversal['response'])
                ]);

                if($reversal['status_code'] != 404){
                    break;
                    return $reversal;

                }

            }else{
                $updateLog = $createLog->update([
                    'response_status' => 'fail',
                    'response' => json_encode($reversal['response']??'')
                ]);
                break;
                return $reversal;
            }
        }

        return $reversal;
    }

    /**
     * Void ovo transaction
     * @param Transaction $transaction Object of transaction join transaction payment ovo or DealsUser join deals payment ovo
     * @return Array ovo response
     */
    static function Void($transaction,$type='trx') {
        $type = env('OVO_ENV');
        if($type == 'production'){
            $url = env('OVO_PROD_URL');
            $tid = env('OVO_PROD_TID');
            $mid = env('OVO_PROD_MID');
            $merchantId = env('OVO_PROD_MERCHANT_ID');
            $storeCode = env('OVO_PROD_STORE_CODE');
            $app_id = env('OVO_PROD_APP_ID');
        }else{
            $url    = env('OVO_STAGING_URL');
            $tid = env('OVO_STAGING_TID');
            $mid = env('OVO_STAGING_MID');
            $merchantId = env('OVO_STAGING_MERCHANT_ID');
            $storeCode = env('OVO_STAGING_STORE_CODE');
            $app_id = env('OVO_STAGING_APP_ID');
        }

        $data['type'] = "0200";
        $data['processingCode'] = "020040";
        $data['amount'] = $transaction['transaction_grandtotal']??$transaction['amount'];

        // $data['date'] = date('Y-m-d H:i:s.v');

        //for millisecond data appears, because if you using a date('Y-m-d H:i:s.v') always return 000
        $datenow = DateTime::createFromFormat('U.u', microtime(true));
        $data['date'] = $datenow->setTimezone(new DateTimeZone('Asia/Jakarta'))->format("Y-m-d H:i:s.v");

        $data['referenceNumber'] = $transaction['reference_number'];
        $data['tid']        = $tid;
        $data['mid']        = $mid;
        $data['merchantId'] = $merchantId;
        $data['storeCode']  = $storeCode;
        $data['appSource']  = 'POS';
        $data['transactionRequestData'] =[
            'batchNo' => $transaction['batch_no'],
            'merchantInvoice' => $transaction['transaction_receipt_number']??$transaction['order_id'],
            'phone' => $transaction['phone']
        ];
        $now = time();

        $header = [
            'hmac' => Self::hmac_value($now),
            'app-id' => $app_id,
            'random' => $now
        ];

        for($i = 1; $i<=3; $i++){

        //create log request
            if($type == 'deals'){
                $logData = [
                    'id_deals_payment_ovo' => $transaction['id_deals_payment_ovo'],
                    'order_id' => $transaction['order_id'],
                    'url' => $url,
                    'header' => json_encode($header),
                    'request' => json_encode($data),
                    'payment_type' => 'Void'
                ];
            }else{
                $logData = [
                    'id_transaction_payment_ovo' => $transaction['id_transaction_payment_ovo'],
                    'transaction_receipt_number' => $transaction['transaction_receipt_number'],
                    'url' => $url,
                    'header' => json_encode($header),
                    'request' => json_encode($data),
                    'payment_type' => 'Void'
                ];
            }

            $reversal = MyHelper::postWithTimeout($url, null, $data, 0, $header);

            if(isset($reversal['status_code'])){
                $reversal['response'] = self::detailResponse($reversal['response']);
                if($type == 'deals'){
                    $logData = array_merge($logData, [
                        'response_status' => 'success',
                        'response_code' => $reversal['status_code'],
                        'response' => json_encode($reversal['response'])
                    ]);

                    if (request()->log_outside) {
                        request()->merge(['doLog' => function() use ($logData) {
                            $createLog = LogOvoDeals::create($logData);
                        }]);
                    } else {
                        $createLog = LogOvoDeals::create($logData);
                    }

                    if($reversal['status_code'] != 404){
                        break;
                        return $reversal;
                    }
                }else{
                    $logData = array_merge($logData, [
                        'response_status' => 'success',
                        'response_code' => $reversal['status_code'],
                        'response' => json_encode($reversal['response'])
                    ]);

                    if (request()->log_outside) {
                        request()->merge(['doLog' => function() use ($logData) {
                            $createLog = LogOvo::create($logData);
                        }]);
                    } else {
                        $createLog = LogOvo::create($logData);
                    }

                    if($reversal['status_code'] != 404){
                        break;
                        return $reversal;

                    }
                }

            }else{
                if($type == 'deals'){
                    $deals_user = DealsUser::where('id_deals_user',$transaction['id_deals_user'])->first();
                    $voucher = DealsVoucher::where('id_deals_voucher',$deals_user['id_deals_voucher'])->update(['deals_voucher_status'=>'Available']);

                    $logData = array_merge($logData, [
                        'response_status' => 'fail',
                        'response' => json_encode($pay)
                    ]);

                    if (request()->log_outside) {
                        request()->merge(['doLog' => function() use ($logData) {
                            $createLog = LogOvoDeals::create($logData);
                        }]);
                    } else {
                        $createLog = LogOvoDeals::create($logData);
                    }

                    break;
                    return $reversal;
                }else{

                    $logData = array_merge($logData, [
                        'response_status' => 'fail',
                        'response' => json_encode($pay)
                    ]);

                    if (request()->log_outside) {
                        request()->merge(['doLog' => function() use ($logData) {
                            $createLog = LogOvo::create($logData);
                        }]);
                    } else {
                        $createLog = LogOvo::create($logData);
                    }

                    break;
                    return $reversal;
                }
            }
        }

        return $reversal;
    }

    //detail response ovo
    static function detailResponse($data){
        if(isset($data['response_code'])){
            if($data['response_code'] == '14'){
               $data['response_detail'] = "Invalid Mobile Number / OVO ID";
               $data['response_description'] = "your mobile number is not registered on OVO";
            }
            elseif($data['response_code'] == '17'){
                $data['response_detail'] = "Transaction Decline";
                $data['response_description'] = "you have canceled the payment";
            }
            elseif($data['response_code'] == '25'){
                $data['response_detail'] = "Transaction Not Found";
                $data['response_description'] = "Payment status not found when called by Check Payment Status API";
            }
            elseif($data['response_code'] == '26'){
                $data['response_detail'] = "Transaction Failed";
                $data['response_description'] = "Failed push payment confirmation to OVO Apps";
            }
            elseif($data['response_code'] == '40'){
                $data['response_detail'] = "Transaction Failed";
                $data['response_description'] = "Failed Push Payment, Error request: Merchant invoice, batch number & reference number already used from previous transactions";
            }
            elseif($data['response_code'] == '54'){
                $data['response_detail'] = "Transaction Expired (More than 7 days)";
                $data['response_description'] = "Transaction details already expired when API check payment status called";
            }
            elseif($data['response_code'] == '56'){
                $data['response_detail'] = "Card Blocked. Please call 1500696";
                $data['response_description'] = "Card is blocked, unable to process card transaction";
            }
            elseif($data['response_code'] == '58'){
                $data['response_detail'] = "Transaction Not Allowed";
                $data['response_description'] = "Transaction module not registered in OVO Systems";
            }
            elseif($data['response_code'] == '61'){
                $data['response_detail'] = "Exceed Transaction Limit";
                $data['response_description'] = "Amount / count exceed limit, set by user";
            }
            elseif($data['response_code'] == '63'){
                $data['response_detail'] = "Security Violation";
                $data['response_description'] = "Authentication Failed";
            }
            elseif($data['response_code'] == '64'){
                $data['response_detail'] = "Account Blocked. Please call 1500696";
                $data['response_description'] = "Account is blocked, unable to process transaction";
            }
            elseif($data['response_code'] == '65'){
                $data['response_detail'] = "Transaction Failed";
                $data['response_description'] = "Limit transaction exceeded, limit on count or amount";
            }
            elseif($data['response_code'] == '67'){
                $data['response_detail'] = "Below Transaction Limit";
                $data['response_description'] = "The transaction amount is less than the minimum payment";
            }
            elseif($data['response_code'] == '68'){
                $data['response_detail'] = "Transaction Pending / Timeout";
                $data['response_description'] = "OVO Wallet late to give respond to OVO JPOS";
            }
            elseif($data['response_code'] == '73'){
                $data['response_detail'] = "Transaction has been reversed";
                $data['response_description'] = "Transaction has been reversed by API Reversal Push to Pay in Check Payment Status API";
            }
            elseif($data['response_code'] == '96'){
                $data['response_detail'] = "Invalid Processing Code";
                $data['response_description'] = "Invalid Processing Code inputted during Call API Check Payment Status";
            }
            elseif($data['response_code'] == 'ER'){
                $data['response_detail'] = "System Failure";
                $data['response_description'] = "There is an error in OVO Systems, Credentials not found in OVO Systems";
            }
            elseif($data['response_code'] == 'EB'){
                $data['response_detail'] = "Terminal Blocked";
                $data['response_description'] = "TID and/or MID not registered in OVO Systems";
            }
        }

        return $data;
    }

    static function checkPaymentStatus($dataTrx, $dataPay) {
        if($type == 'production'){
            $url = env('OVO_PROD_URL');
            $app_id = env('OVO_PROD_APP_ID');
        }else{
            $url    = env('OVO_STAGING_URL');
            $app_id = env('OVO_STAGING_APP_ID');
        }

        $data['type'] = "0100";
        $data['processingCode'] = "040000";
        $data['amount'] = (int)$dataTrx['transaction_grandtotal'];

        // $data['date'] = date('Y-m-d H:i:s.v');

        //for millisecond data appears, because if you using a date('Y-m-d H:i:s.v') always return 000
        $datenow = DateTime::createFromFormat('U.u', microtime(true));
        $data['date'] = $datenow->setTimezone(new DateTimeZone('Asia/Jakarta'))->format("Y-m-d H:i:s.v");

        $data['referenceNumber'] = $dataPay['reference_number'];
        $data['tid'] = env('OVO_TID');
        $data['mid'] = env('OVO_MID');
        $data['merchantId'] = env('OVO_MERCHANT_ID');
        $data['storeCode'] = env('OVO_STORE_CODE');
        $data['appSource'] = 'POS';
        $data['transactionRequestData'] =[
            'batchNo' => $dataPay['batch_no'],
            'phone' => $dataPay['phone'],
            'merchantInvoice' => $dataTrx['transaction_receipt_number'],
        ];

        $now = time();

        $header = [
            'hmac' => Self::hmac_value($now),
            'app_id' => $app_id.$now,
            'random' => $now
        ];

        $check = MyHelper::post($url, null, $data, 0, $header);

        return $check;
    }
}
?>

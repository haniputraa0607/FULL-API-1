<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\Configs;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionPaymentNobu;
use App\Http\Models\LogNobu;
use App\Http\Models\User;
use App\Http\Models\Outlet;
use App\Http\Models\LogBalance;
use App\Http\Models\LogTopup;
use App\Http\Models\UsersMembership;
use App\Http\Models\Setting;
use App\Http\Models\UserOutlet;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPickupGoSend;
use App\Http\Models\TransactionShipment;
use App\Http\Models\LogPoint;
use App\Http\Models\FraudSetting;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;
use App\Lib\PushNotificationHelper;

use App\Jobs\FraudJob;
use GuzzleHttp\Client;
use App\Lib\Nobu;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;
use App\Lib\GoSend;
use Hash;
use DB;

class ApiNobuController extends Controller
{

    public function __construct()
    {
        $this->get_login      = env('NOBU_LOGIN', 'MAXX');
        $this->get_password   = env('NOBU_PASSWORD', 'MAXX');
        $this->get_secret_key = env('NOBU_SECRET_KEY', 'SecretNobuKey');
        $this->setting_fraud  = "Modules\SettingFraud\Http\Controllers\ApiFraud";
        $this->notif          = "Modules\Transaction\Http\Controllers\ApiNotification";
        $this->autocrm        = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->balance        = "Modules\Balance\Http\Controllers\BalanceController";
        $this->promo_campaign = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->voucher        = "Modules\Deals\Http\Controllers\ApiDealsVoucher";

    }

    public function notifNobu(Request $request)
    {
        $post = $request->json()->all();
        $header = $request->header();
        $data = json_decode(base64_decode($post['data']),true) ?? [];
        $success = true;

        $validSignature = $this->nobuSignature($data);
        if ($data['signature'] != $validSignature) {
            $success = false;
            goto end;
        }

        DB::beginTransaction();
        if (stristr($data['transactionNo'], "TRX")) {
            $trx = Transaction::where('transaction_receipt_number', $data['transactionNo'])->join('transaction_payment_nobu', 'transactions.id_transaction', '=', 'transaction_payment_nobu.id_transaction')->first();
            if (!$trx) {
                DB::rollBack();
                $success = false;
                goto end;
            }
            if ($trx->gross_amount != $data['amount']) {
                DB::rollBack();
                $success = false;
                goto end;
            }
            if (strtolower($data['paymentStatus']) == 'paid') {
                $update = $trx->update(['transaction_payment_status' => 'Completed', 'completed_at' => date('Y-m-d H:i:s')]);
                if ($update) {
                    $userData               = User::where('id', $trx['id_user'])->first();
                    $config_fraud_use_queue = Configs::where('config_name', 'fraud use queue')->first()->is_active;

                    if ($config_fraud_use_queue == 1) {
                        FraudJob::dispatch($userData, $trx, 'transaction')->onConnection('fraudqueue');
                    } else {
                        $checkFraud = app($this->setting_fraud)->checkFraudTrxOnline($userData, $trx);
                    }
                }else{
                    DB::rollBack();
                    $success = false;
                    goto end;
                }
            }else{
                DB::rollBack();
                $success = false;
                goto end;
            }

             //inset pickup_at when pickup_type = right now
             if ($trx['trasaction_type'] == 'Pickup Order') {
                $detailTrx = TransactionPickup::where('id_transaction', $trx->id_transaction)->first();
                if ($detailTrx['pickup_type'] == 'right now') {
                    $settingTime = MyHelper::setting('processing_time');
                    if ($settingTime) {
                        $updatePickup = $detailTrx->update(['pickup_at' => date('Y-m-d H:i:s', strtotime('+ ' . $settingTime . 'minutes'))]);
                    } else {
                        $updatePickup = $detailTrx->update(['pickup_at' => date('Y-m-d H:i:s')]);
                    }
                }
            }

            $no_transaction_reference = $this->getTransactionReference($data);

            TransactionPaymentNobu::where('id_transaction', $trx->id_transaction)->update([
                'no_transaction_reference'  => $no_transaction_reference,
                'payment_status'            => $data['paymentStatus'] ?? null,
                'payment_reference_no'      => $data['paymentReferenceNo'] ?? null,
                'payment_date'              => $data['paymentDate'] ?? null,
                'id_issuer'                 => $data['issuerID'] ?? null,
                'retrieval_reference_no'    => $data['retrievalReferenceNo'] ?? null,
            ]);
            DB::commit();

            $trx->load('outlet');
            $trx->load('productTransaction');

            $mid = [
                'order_id'     => $trx['transaction_receipt_number'],
                'gross_amount' => ($trx['amount'] / 100),
            ];
            $send = app($this->notif)->notification($mid, $trx);

            if ($trx['id_transaction']??false) {
                $pickup = TransactionPickup::where('id_transaction', $trx['id_transaction'])->first();
                if ($pickup) {
                    if ($pickup->pickup_by == 'GO-SEND') {
                        $pickup->bookDelivery();
                    } else {
                        \App\Lib\ConnectPOS::create()->sendTransaction($trx['id_transaction']);
                    }
                }
            }

        }else{
            DB::rollBack();
            $success = false;
            goto end;
        }

        end:
        if($success){
            $send = [
                "responseStatus"      => "Success",
                "responseCode"        => "211000",
                "responseDescription" => "Transaction Success",
                "messageDetail"       => "success",
                "data" => [
                    "transactionNo"          => $data['transactionNo'],
                    "referenceNo"            => $data['referenceNo'],
                    "referenceTransactionNo" => $no_transaction_reference,
                    "transactionStatus"      => "VALID",
                    "amount"                 => $data['amount'],
                    "paymentStatus"          => "PAID",
                    "paymentReferenceNo"     => $data['paymentReferenceNo'],
                    "paymentDate"            => $data['paymentDate'],
                    "issuerID"               => $data['issuerID'],
                    "retrievalReferenceNo"   => $data['retrievalReferenceNo'],
                ]
            ];
        }else{
            $send = [
                "responseStatus"      => "Failed",
                "responseCode"        => "211001",
                "responseDescription" => "Invalid Transaction",
                "messageDetail"       => "Payment confirmation is failed and cannot be verified",
                "data" => [
                    "transactionNo"          => $data['transactionNo'],
                    "referenceNo"            => $data['referenceNo'],
                    "referenceTransactionNo" => "",
                    "transactionStatus"      => "INVALID",
                    "amount"                 => $data['amount'],
                    "paymentStatus"          => "UNPAID",
                    "paymentReferenceNo"     => "",
                    "paymentDate"            => "",
                    "issuerID"               => "",
                    "retrievalReferenceNo"   => "",
                ]
            ];
        }

        try {
            LogNobu::create([
                'type'                  => 'confirm_payment',
                'id_reference'          => $trx['id_transaction']??'',
                'request_url'           => url(route('notif_nobu')),
                'request'               => json_encode($post),
                'request_header'        => json_encode($header),
                'response'              => json_encode($send),
                'response_status_code'  => 200,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed write log to LogNobu: ' . $e->getMessage());
        }
        
        $response = [
            "data" => base64_encode(json_encode($send))
        ];

        return $response;
    }

    public function nobuSignature($data)
    {
        $return = md5($this->get_login.$this->get_password.$data['transactionNo'].$data['referenceNo'].$data['amount'].$data['paymentStatus'].$data['paymentReferenceNo'].$data['paymentDate'].$data['issuerID'].$data['retrievalReferenceNo'].$this->get_secret_key);
        return $return;
    }

    public function getTransactionReference($data){
        $no = $data['transactionNo'].'-'.date('ymd').'-'.rand(100000,999999);
        $check = TransactionPaymentNobu::where('no_transaction_reference',$no)->first();
        if($check){
            $this->getTransactionReference($data);
        }
        return $no;
    }

    public function checkTransactionPayment($id_transaction){
        $trx = Transaction::with(['transaction_payment_nobu'])->where('id_transaction',$id_transaction)->first();

        if(!$trx){
            return [
                'status' => false,
                'message' => 'Transaction not found'
            ];
        }

        if(!$trx['transaction_payment_nobu']){
            return [
                'status' => false,
                'message' => 'Transaction not found'
            ];
        }

        $check = Nobu::InquiryPaymentStatus($trx,'inquiry_payment_status',$trx['id_transaction']);
        if($check && $check['status_code'] == 200){
            $responeNobu = json_decode(base64_decode($check['response']['data']),true) ?? [];
            if(strtolower($responeNobu['responseStatus'])=='failed' && strtolower($responeNobu['data']['paymentStatus'])=='unpaid'){
                return [
                    'status' => true,
                    'message' => 'Transaction unpaid'
                ];
            }elseif(strtolower($responeNobu['responseStatus'])=='success' && strtolower($responeNobu['data']['paymentStatus'])=='paid'){
                DB::beginTransaction();
                
                $update = $trx->update(['transaction_payment_status' => 'Completed', 'completed_at' => date('Y-m-d H:i:s')]);
                if ($update) {
                    $userData               = User::where('id', $trx['id_user'])->first();
                    $config_fraud_use_queue = Configs::where('config_name', 'fraud use queue')->first()->is_active;

                    if ($config_fraud_use_queue == 1) {
                        FraudJob::dispatch($userData, $trx, 'transaction')->onConnection('fraudqueue');
                    } else {
                        $checkFraud = app($this->setting_fraud)->checkFraudTrxOnline($userData, $trx);
                    }
                }
                if ($trx['trasaction_type'] == 'Pickup Order') {
                    $detailTrx = TransactionPickup::where('id_transaction', $trx['id_transaction'])->first();
                    if ($detailTrx['pickup_type'] == 'right now') {
                        $settingTime = MyHelper::setting('processing_time');
                        if ($settingTime) {
                            $updatePickup = $detailTrx->update(['pickup_at' => date('Y-m-d H:i:s', strtotime('+ ' . $settingTime . 'minutes'))]);
                        } else {
                            $updatePickup = $detailTrx->update(['pickup_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }

                if(!$trx['transaction_payment_nobu']['no_transaction_reference']){
                    $no_transaction_reference = $this->getTransactionReference($responeNobu['data']);
                }else{
                    $no_transaction_reference = $responeNobu['data']['referenceTransactionNo'];
                }

                TransactionPaymentNobu::where('id_transaction', $trx['id_transaction'])->update([
                    'no_transaction_reference'  => $no_transaction_reference,
                    'payment_status'            => $responeNobu['data']['paymentStatus'] ?? null,
                    'payment_reference_no'      => $responeNobu['data']['paymentReferenceNo'] ?? null,
                    'payment_date'              => $responeNobu['data']['paymentDate'] ?? null,
                    'id_issuer'                 => $responeNobu['data']['issuerID'] ?? null,
                    'retrieval_reference_no'    => $responeNobu['data']['retrievalReferenceNo'] ?? null,
                ]);
                DB::commit();

                $trx->load('outlet');
                $trx->load('productTransaction');

                $mid = [
                    'order_id'     => $trx['transaction_receipt_number'],
                    'gross_amount' => ($trx['amount'] / 100),
                ];
                $send = app($this->notif)->notification($mid, $trx);

                if ($trx['id_transaction']??false) {
                    $pickup = TransactionPickup::where('id_transaction', $trx['id_transaction'])->first();
                    if ($pickup) {
                        if ($pickup->pickup_by == 'GO-SEND') {
                            $pickup->bookDelivery();
                        } else {
                            \App\Lib\ConnectPOS::create()->sendTransaction($trx['id_transaction']);
                        }
                    }
                }

                return [
                    'status' => false,
                    'message' => 'Transaction has been paid'
                ];
            }
        }else{
            return [
                'status' => false,
                'message' => 'Cant get data from nobu'
            ];
        }
    }

    public function cancelTransactionPayment($id_transaction){

        $trx = Transaction::with(['transaction_payment_nobu','user'])->where('id_transaction',$id_transaction)->first();

        if(!$trx){
            return false;
        }

        if(!$trx['transaction_payment_nobu']){
            return false;
        }

        $cancel = Nobu::CancelingDynamicQRIS($trx,'cancel_qris',$trx['id_transaction']);
        if($cancel && $cancel['status_code'] == 200){
            $responeNobu = json_decode(base64_decode($cancel['response']['data']),true) ?? [];
            if(strtolower($responeNobu['responseStatus'])=='failed'){
                return false;
            }elseif(strtolower($responeNobu['responseStatus'])=='success' && strtolower($responeNobu['data']['qrisStatus'])=='canceled'){ 
                TransactionPaymentNobu::where('id_transaction', $trx['id_transaction'])->update([
                    'payment_status'     => 'UNPAID',
                    'status_message'     => $responeNobu['messageDetail'],
                ]);
                return true;
            }
        }else{
            return false;
        }

    }

    public function cronCheck(){

        $log = MyHelper::logCron('Cancel Transaction Nobu');
        try {
            $now       = date('Y-m-d H:i:s');
            $expired   = date('Y-m-d H:i:s',strtotime('- 5minutes'));

            $getTrx = Transaction::where('transaction_payment_status', 'Pending')
                ->where('transaction_date', '<=', $expired)
                ->where(function ($query) {
                    $query->where('trasaction_payment_type', 'Nobu')
                        ->orWhere('trasaction_payment_type', 'Balance');
                })
                ->where(function ($query) {
                    $query->whereNull('latest_reversal_process')
                        ->orWhere('latest_reversal_process', '<', date('Y-m-d H:i:s', strtotime('- 5 minutes')));
                })
                ->get();

            foreach ($getTrx as $i => $trans) {
                $checkMultiple = TransactionMultiplePayment::where('id_transaction', $trans->id_transaction)->where('type','Nobu')->first();
                if(!$checkMultiple){
                    unset($getTrx[$i]);
                }
            }
            
            Transaction::fillLatestReversalProcess($getTrx);

            if (empty($getTrx)) {
                $log->success('empty');
                return response()->json(['empty']);
            }

            $count = 0;
            foreach ($getTrx as $key => $singleTrx) {
                $singleTrx->load('outlet_name');

                $productTrx = TransactionProduct::where('id_transaction', $singleTrx->id_transaction)->get();
                if (empty($productTrx)) {
                    $singleTrx->clearLatestReversalProcess();
                    continue;
                }

                $user = User::where('id', $singleTrx->id_user)->first();
                if (empty($user)) {
                    $singleTrx->clearLatestReversalProcess();
                    continue;
                }

                $checkMultiple = TransactionMultiplePayment::where('id_transaction', $singleTrx->id_transaction)->get()->pluck('type')->toArray();
                if($singleTrx->trasaction_payment_type == 'Nobu' || in_array('Nobu',$checkMultiple)) {
                    $checktNobu = $this->checkTransactionPayment($singleTrx->id_transaction);
                    if(!$checktNobu['status'] && $checktNobu['message'] == 'Transaction has been paid'){
                        $singleTrx->clearLatestReversalProcess();
                        continue;
                    }elseif($checktNobu['status'] && $checktNobu['message'] == 'Transaction unpaid'){
                       $cancelNobu = $this->cancelTransactionPayment($singleTrx->id_transaction);
                       if(!$cancelNobu){
                            $singleTrx->clearLatestReversalProcess();
                            continue;
                       }
                    }

                }else{
                    continue;
                }

                DB::begintransaction();

                MyHelper::updateFlagTransactionOnline($singleTrx, 'cancel', $user);

                $singleTrx->transaction_payment_status = 'Cancelled';
                $singleTrx->void_date = $now;
                $update = $singleTrx->save();

                if (!$update) {
                    DB::rollBack();
                    $singleTrx->clearLatestReversalProcess();
                    continue;
                }

                $logBalance = LogBalance::where('id_reference', $singleTrx->id_transaction)->where('source', 'Transaction')->where('balance', '<', 0)->get();
                foreach($logBalance as $logB){
                    $reversal = app($this->balance)->addLogBalance( $singleTrx->id_user, abs($logB['balance']), $singleTrx->id_transaction, 'Reversal', $singleTrx->transaction_grandtotal);
    	            if (!$reversal) {
    	            	DB::rollBack();
                        $singleTrx->clearLatestReversalProcess();
    	            	continue;
    	            }
                    $usere= User::where('id',$singleTrx->id_user)->first();
                    $send = app($this->autocrm)->SendAutoCRM('Transaction Failed Point Refund', $usere->phone,
                        [
                            "outlet_name"       => $singleTrx->outlet_name->outlet_name,
                            "transaction_date"  => $singleTrx->transaction_date,
                            'id_transaction'    => $singleTrx->id_transaction,
                            'receipt_number'    => $singleTrx->transaction_receipt_number,
                            'received_point'    => (string) abs($logB['balance'])
                        ]
                    );
                }

                // delete promo campaign report
                if ($singleTrx->id_promo_campaign_promo_code) {
                	$update_promo_report = app($this->promo_campaign)->deleteReport($singleTrx->id_transaction, $singleTrx->id_promo_campaign_promo_code);
                	if (!$update_promo_report) {
    	            	DB::rollBack();
    	            	continue;
    	            }
                }

                // return voucher
                $update_voucher = app($this->voucher)->returnVoucher($singleTrx->id_transaction);
                if (!$update_voucher) {
                	DB::rollBack();
                	continue;
                }
                $count++;
                DB::commit();
            }

            $log->success([$count]);
            return response()->json([$count]);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }


}

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
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;

use App\Jobs\FraudJob;
use GuzzleHttp\Client;

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
        $this->get_login      = 'MAXX';
        $this->get_password   = 'MAXX';
        $this->get_merchant   = '936005030000049084';
        $this->get_store      = 'ID2020081400327';
        $this->get_pos        = 'A01';
        $this->get_secret_key = 'SecretNobuKey';
        $this->setting_fraud  = "Modules\SettingFraud\Http\Controllers\ApiFraud";
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
            if ($data['paymentStatus'] == 'PAID') {
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
                'request'               => $post,
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



}

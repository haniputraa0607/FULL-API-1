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

        $post = $request->post();
        $header = $request->header();
        $data = json_decode(base64_decode($post['data']),true) ?? [];

        $validSignature = $this->nobuSignature($data);
        if ($data['signature'] != $validSignature) {
            $status_code = 401;
            $response    = [
                'status'   => 'fail',
                'messages' => ['Signature mismatch'],
            ];
            goto end;
        }

        DB::beginTransaction();
        if (stristr($data['transactionNo'], "TRX")) {
            $trx = Transaction::where('transaction_receipt_number', $data['transactionNo'])->join('transaction_payment_nobu', 'transactions.id_transaction', '=', 'transaction_payment_nobu.id_transaction')->first();
            if (!$trx) {
                DB::rollBack();
                $status_code = 404;
                $response    = ['status' => 'fail', 'messages' => ['Transaction not found']];
                goto end;
            }
            if ($trx->gross_amount != $data['amount']) {
                DB::rollBack();
                $status_code = 401;
                $response    = ['status' => 'fail', 'messages' => ['Invalid amount']];
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
                }
            }

            if (!$update) {
                DB::rollBack();
                $status_code = 500;
                $response    = [
                    'status'   => 'fail',
                    'messages' => ['Failed update payment status'],
                ];
            }

        }else{
            DB::rollBack();
            $status_code = 400;
            $response    = ['status' => 'fail', 'messages' => ['Transaction receipt number is invalid']];
            goto end;
        }

        end:
        try {
            LogNobu::create([
                'type'                  => 'confirm_payment',
                'id_reference'          => $trx['id_transaction']??'',
                'request_url'           => url(route('notif_nobu')),
                'request'               => $post,
                'request_header'        => json_encode($header),
                'response'              => json_encode($log_response),
                'response_status_code'  => $response['status_code']??'',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed write log to LogNobu: ' . $e->getMessage());
        }
        return response()->json($response, $status_code);
        
    }

    public function nobuSignature($data)
    {
        $return = md5($this->get_login.$this->get_password.$data['transactionNo'].$data['referenceNo'].$data['amount'].$data['paymentStatus'].$data['paymentReferenceNo'].$data['paymentDate'].$data['issuerID'].$data['retrievalReferenceNo'].$this->get_secret_key);
        return $return;
    }



}

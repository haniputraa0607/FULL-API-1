<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\LogBackendError;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionMultiplePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Transaction\Entities\TransactionPaymentCimb;

class ApiTransactionCIMB extends Controller
{
    function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm       = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
    }

    public function callback(Request $request)
    {
        if ($request['RESPONSE_CODE'] == 0) {
            $transaction = Transaction::with('user.memberships', 'outlet', 'productTransaction')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->first();

            DB::beginTransaction();
            try {
                $addCimb = TransactionPaymentCimb::create([
                    'id_transaction'    => $transaction->id_transaction,
                    'transaction_id'    => $request['TRANSACTION_ID'],
                    'txn_status'        => $request['TXN_STATUS'],
                    'txn_signature'     => $request['TXN_SIGNATURE'],
                    'secure_signature'  => $request['SECURE_SIGNATURE'],
                    'tran_date'         => $request['TRAN_DATE'],
                    'merchant_tranid'   => $request['MERCHANT_TRANID'],
                    'response_code'     => $request['RESPONSE_CODE'],
                    'response_desc'     => $request['RESPONSE_DESC'],
                    'auth_id'           => $request['AUTH_ID'],
                    'fr_level'          => $request['FR_LEVEL'],
                    'sales_date'        => $request['SALES_DATE'],
                    'fr_score'          => $request['FR_SCORE']
                ]);
            } catch (\Exception $e) {
                LogBackendError::logExceptionMessage("ApiTransactionCIMB/callback=>" . $e->getMessage(), $e);
                DB::rollback();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => [
                        'Something when wrong!. Contact Admin Support.'
                    ]
                ]);
            }

            try {
                Transaction::where('transaction_receipt_number', $request['MERCHANT_TRANID'])->update([
                    'transaction_payment_status'    => 'Completed'
                ]);
            } catch (\Exception $e) {
                LogBackendError::logExceptionMessage("ApiTransactionCIMB/callback=>" . $e->getMessage(), $e);
                DB::rollback();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => [
                        'Something when wrong!. Contact Admin Support.'
                    ]
                ]);
            }

            $dataMultiple = [
                'id_transaction' => $transaction->id_transaction,
                'type'           => 'Cimb',
                'id_payment'     => $addCimb->id_transaction_payment_cimb
            ];

            $saveMultiple = TransactionMultiplePayment::create($dataMultiple);
            if (!$saveMultiple) {
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => [
                        'fail to confirm transaction'
                    ]
                ]);
            }

            // apply cashback to referrer
            \Modules\PromoCampaign\Lib\PromoCampaignTools::applyReferrerCashback($transaction);

            $mid = [
                'order_id' => $transaction->transaction_receipt_number,
                'gross_amount' => $transaction->transaction_subtotal
            ];

            $notif = app($this->notif)->notification($mid, $transaction);
            if (!$notif) {
                DB::rollBack();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Transaction Notification failed']
                ]);
            }
            $sendNotifOutlet = app($this->trx)->outletNotif($transaction->id_transaction);

            //create geocode location
            if (isset($transaction->latitude) && isset($transaction->longitude)) {
                $savelocation = app($this->trx)->saveLocation($transaction->latitude, $transaction->longitude, $transaction->id_user, $transaction->id_transaction, $transaction->id_outlet);
            }

            DB::commit();

            $dataEncode = [
                'transaction_receipt_number'   => $request['MERCHANT_TRANID'],
                'type' => 'trx',
                'trx_success' => 1
            ];
            $encode = json_encode($dataEncode);
            $base = base64_encode($encode);

            return response()->json([
                'status'           => 'success',
                'url'              => env('API_URL') . 'api/transaction/web/view/detail?data=' . $base

            ]);
        } else {
            return response()->json([
                'status'    => 'fail',
                'messages'  => [
                    'Payment via CIMB failed.'
                ]
            ]);
        }
    }
}

<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\LogBackendError;
use App\Http\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Transaction\Entities\TransactionPaymentCimb;

class ApiTransactionCIMB extends Controller
{
    public function callback(Request $request)
    {
        if ($request['RESPONSE_CODE'] == 0) {
            $transaction = Transaction::where('transaction_receipt_number', $request['MERCHANT_TRANID'])->first();

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

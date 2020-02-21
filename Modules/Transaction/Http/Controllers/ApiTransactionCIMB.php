<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\DealsUser;
use App\Http\Models\LogBackendError;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionMultiplePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Deals\Entities\DealsPaymentCimb;
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

    public function callbackDeals(Request $request)
    {
        if ($request['RESPONSE_CODE'] == 0) {
            $idVoucher = explode('-', $request['MERCHANT_TRANID'])[1];
            $voucher = DealsUser::with(['userMid', 'dealVoucher'])->where('id_deals_user', $idVoucher)->first();

            DB::beginTransaction();

            try {
                DealsPaymentCimb::create([
                    'id_deals'          => $voucher->id_deals,
                    'id_deals_user'     => $voucher->id_deals_user,
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
                LogBackendError::logExceptionMessage("ApiTransactionCIMB/callbackDeals=>" . $e->getMessage(), $e);
                DB::rollback();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => [
                        'Something when wrong!. Contact Admin Support.'
                    ]
                ]);
            }

            if ($voucher->deal_voucher->id_deals_subscription != null && $voucher->paid_status == "Completed") {
                $total_voucher_subs = $voucher->deals->total_voucher_subscription;
                $voucher_subs_ids = DealsUser::with(['userMid', 'dealVoucher'])
                    ->where('id_deals', $voucher->id_deals)
                    ->where('id_user', $voucher->id_user)
                    ->latest()
                    ->take($total_voucher_subs)
                    ->pluck('id_deals_user')->toArray();

                $update = DealsUser::whereIn('id_deals_user', $voucher_subs_ids)->update(['paid_status' => "Completed"]);
                // update voucher to multi vouchers
                $pay['voucher'] = DealsUser::whereIn('id_deals_user', $voucher_subs_ids)->get();

                if ($pay && $update) {
                    DB::commit();
                    return response()->json(MyHelper::checkCreate($pay));
                }
            } elseif ($pay) {
                DB::commit();
                $return = MyHelper::checkCreate($pay);
                if (isset($return['status']) && $return['status'] == 'success') {
                    if (\Module::collections()->has('Autocrm')) {
                        $phone = User::where('id', $voucher->id_user)->pluck('phone')->first();
                        $voucher->load('dealVoucher.deals');
                        $autocrm = app($this->autocrm)->SendAutoCRM(
                            'Claim Paid Deals Success',
                            $phone,
                            [
                                'claimed_at'                => $voucher->claimed_at,
                                'deals_title'               => $voucher->dealVoucher->deals->deals_title,
                                'id_deals_user'             => $return['result']['voucher']['id_deals_user'],
                                'deals_voucher_price_point' => (string) $voucher->voucher_price_point,
                                'id_deals'                  => $voucher->dealVoucher->deals->id_deals,
                                'id_brand'                  => $voucher->dealVoucher->deals->id_brand
                            ]
                        );
                    }
                    $result = [
                        'id_deals_user' => $return['result']['voucher']['id_deals_user'],
                        'id_deals_voucher' => $return['result']['voucher']['id_deals_voucher'],
                        'paid_status' => $return['result']['voucher']['paid_status'],
                    ];
                    if (isset($return['result']['midtrans'])) {
                        $result['redirect'] = true;
                        $result['midtrans'] = $return['result']['midtrans'];
                    } elseif (isset($return['result']['ovo'])) {
                        $result['redirect'] = true;
                        $result['ovo'] = $return['result']['ovo'];
                    } else {
                        $result['redirect'] = false;
                    }
                    $result['webview_later'] = env('API_URL') . 'api/webview/mydeals/' . $return['result']['voucher']['id_deals_user'];
                    unset($return['result']);
                    $return['result'] = $result;
                }
                return response()->json($return);
            }
        }
    }
}

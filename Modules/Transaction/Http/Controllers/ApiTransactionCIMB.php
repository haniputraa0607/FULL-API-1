<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\DealsUser;
use App\Http\Models\LogBackendError;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionShipment;
use App\Http\Models\User;
use App\Lib\MyHelper;
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
            $getCimb = TransactionPaymentCimb::where('merchant_tranid', $request['MERCHANT_TRANID'])->first();
            $transaction = Transaction::with('user.memberships', 'outlet', 'productTransaction')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->first();

            DB::beginTransaction();

            try {
                $updateCimb = TransactionPaymentCimb::where('merchant_tranid', $request['MERCHANT_TRANID'])
                    ->update([
                        'id_transaction'    => $transaction->id_transaction,
                        'transaction_id'    => $request['TRANSACTION_ID'],
                        'txn_status'        => $request['TXN_STATUS'],
                        'txn_signature'     => $request['TXN_SIGNATURE'],
                        'secure_signature'  => $request['SECURE_SIGNATURE'],
                        'tran_date'         => $request['TRAN_DATE'],
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
                $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
                if ($data['trasaction_type'] == 'Pickup Order') {
                    $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
                } elseif ($data['trasaction_type'] == 'Delivery') {
                    $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
                }

                $data['detail'] = $detail;

                return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
            }

            try {
                Transaction::where('transaction_receipt_number', $request['MERCHANT_TRANID'])->update([
                    'transaction_payment_status'    => 'Completed'
                ]);
            } catch (\Exception $e) {
                LogBackendError::logExceptionMessage("ApiTransactionCIMB/callback=>" . $e->getMessage(), $e);
                DB::rollback();
                $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
                if ($data['trasaction_type'] == 'Pickup Order') {
                    $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
                } elseif ($data['trasaction_type'] == 'Delivery') {
                    $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
                }

                $data['detail'] = $detail;

                return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
            }

            $dataMultiple = [
                'id_transaction' => $transaction->id_transaction,
                'type'           => 'Cimb',
                'id_payment'     => $getCimb->idtransaction_payment_cimb
            ];

            $saveMultiple = TransactionMultiplePayment::create($dataMultiple);
            if (!$saveMultiple) {
                DB::rollback();
                $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
                if ($data['trasaction_type'] == 'Pickup Order') {
                    $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
                } elseif ($data['trasaction_type'] == 'Delivery') {
                    $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
                }

                $data['detail'] = $detail;

                return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
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
                $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
                if ($data['trasaction_type'] == 'Pickup Order') {
                    $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
                } elseif ($data['trasaction_type'] == 'Delivery') {
                    $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
                }

                $data['detail'] = $detail;

                return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
            }
            $sendNotifOutlet = app($this->trx)->outletNotif($transaction->id_transaction);

            //create geocode location
            if (isset($transaction->latitude) && isset($transaction->longitude)) {
                $savelocation = app($this->trx)->saveLocation($transaction->latitude, $transaction->longitude, $transaction->id_user, $transaction->id_transaction, $transaction->id_outlet);
            }

            DB::commit();

            $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
            if ($data['trasaction_type'] == 'Pickup Order') {
                $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
                $qrTest = $detail['order_id'];
            } elseif ($data['trasaction_type'] == 'Delivery') {
                $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
            }

            $qrCode = 'https://chart.googleapis.com/chart?chl=' . $qrTest . '&chs=250x250&cht=qr&chld=H%7C0';
            $qrCode = html_entity_decode($qrCode);
            $data['qr'] = $qrCode;
            $data['detail'] = $detail;

            return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
        } else {
            $getCimb = TransactionPaymentCimb::where('merchant_tranid', $request['MERCHANT_TRANID'])->first();
            $transaction = Transaction::with('user')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->first();

            DB::beginTransaction();

            try {
                $updateCimb = TransactionPaymentCimb::where('merchant_tranid', $request['MERCHANT_TRANID'])
                    ->update([
                        'id_transaction'    => $transaction->id_transaction,
                        'transaction_id'    => $request['TRANSACTION_ID'],
                        'txn_status'        => $request['TXN_STATUS'],
                        'txn_signature'     => $request['TXN_SIGNATURE'],
                        'secure_signature'  => $request['SECURE_SIGNATURE'],
                        'tran_date'         => $request['TRAN_DATE'],
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
                $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
                if ($data['trasaction_type'] == 'Pickup Order') {
                    $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
                } elseif ($data['trasaction_type'] == 'Delivery') {
                    $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
                }

                $data['detail'] = $detail;

                return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
            }

            try {
                Transaction::where('transaction_receipt_number', $request['MERCHANT_TRANID'])->update([
                    'transaction_payment_status'    => 'Cancelled'
                ]);
            } catch (\Exception $e) {
                LogBackendError::logExceptionMessage("ApiTransactionCIMB/callback=>" . $e->getMessage(), $e);
                DB::rollback();
                $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
                if ($data['trasaction_type'] == 'Pickup Order') {
                    $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
                } elseif ($data['trasaction_type'] == 'Delivery') {
                    $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
                }

                $data['detail'] = $detail;

                return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
            }

            DB::commit();

            $data = Transaction::with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->where('transaction_receipt_number', $request['MERCHANT_TRANID'])->get()->toArray()[0];
            if ($data['trasaction_type'] == 'Pickup Order') {
                $detail = TransactionPickup::where('id_transaction', $data['id_transaction'])->with('transaction_pickup_go_send')->first();
            } elseif ($data['trasaction_type'] == 'Delivery') {
                $detail = TransactionShipment::with('city.province')->where('id_transaction', $data['id_transaction'])->first();
            }

            $data['detail'] = $detail;

            return view('transaction::webview.detail_transaction_pickup')->with(compact('data'));
        }
    }

    public function callbackDeals(Request $request)
    {
        if ($request['RESPONSE_CODE'] == 0) {
            $idVoucher = explode('-', $request['MERCHANT_TRANID'])[1];
            $voucher = DealsUser::with(['userMid', 'dealVoucher'])->where('id_deals_user', $idVoucher)->first();

            DB::beginTransaction();

            try {
                DealsPaymentCimb::where('merchant_tranid', $request['MERCHANT_TRANID'])
                    ->update([
                        'id_deals'          => $voucher->id_deals,
                        'id_deals_user'     => $voucher->id_deals_user,
                        'transaction_id'    => $request['TRANSACTION_ID'],
                        'txn_status'        => $request['TXN_STATUS'],
                        'txn_signature'     => $request['TXN_SIGNATURE'],
                        'secure_signature'  => $request['SECURE_SIGNATURE'],
                        'tran_date'         => $request['TRAN_DATE'],
                        'response_code'     => $request['RESPONSE_CODE'],
                        'response_desc'     => $request['RESPONSE_DESC'],
                        'auth_id'           => $request['AUTH_ID'],
                        'fr_level'          => $request['FR_LEVEL'],
                        'sales_date'        => $request['SALES_DATE'],
                        'fr_score'          => $request['FR_SCORE']
                    ]);

                DealsUser::where('id_deals_user', $idVoucher)->update(['paid_status' => 'Completed']);
            } catch (\Exception $e) {
                LogBackendError::logExceptionMessage("ApiTransactionCIMB/callbackDeals=>" . $e->getMessage(), $e);
                DB::rollback();
                return redirect('#transaction_fail');
            }

            DB::commit();

            $voucher = DealsUser::with(['userMid', 'dealVoucher'])->where('id_deals_user', $idVoucher)->first();
            if (\Module::collections()->has('Autocrm')) {
                $phone = User::where('id', $voucher->id_user)->pluck('phone')->first();
                $voucher->load('dealVoucher.deals');

                $autocrm = app($this->autocrm)->SendAutoCRM(
                    'Claim Paid Deals Success',
                    $phone,
                    [
                        'claimed_at'                => $voucher->claimed_at,
                        'deals_title'               => $voucher->dealVoucher->deals->deals_title,
                        'id_deals_user'             => $idVoucher,
                        'deals_voucher_price_point' => (string) $voucher->voucher_price_point,
                        'id_deals'                  => $voucher->dealVoucher->deals->id_deals,
                        'id_brand'                  => $voucher->dealVoucher->deals->id_brand
                    ]
                );
            }

            return redirect('#transaction_success');
        } else {
            $idVoucher = explode('-', $request['MERCHANT_TRANID'])[1];
            $voucher = DealsUser::with(['userMid', 'dealVoucher'])->where('id_deals_user', $idVoucher)->first();

            DB::beginTransaction();

            try {
                DealsPaymentCimb::where('merchant_tranid', $request['MERCHANT_TRANID'])
                    ->update([
                        'id_deals'          => $voucher->id_deals,
                        'id_deals_user'     => $voucher->id_deals_user,
                        'transaction_id'    => $request['TRANSACTION_ID'],
                        'txn_status'        => $request['TXN_STATUS'],
                        'txn_signature'     => $request['TXN_SIGNATURE'],
                        'secure_signature'  => $request['SECURE_SIGNATURE'],
                        'tran_date'         => $request['TRAN_DATE'],
                        'response_code'     => $request['RESPONSE_CODE'],
                        'response_desc'     => $request['RESPONSE_DESC'],
                        'auth_id'           => $request['AUTH_ID'],
                        'fr_level'          => $request['FR_LEVEL'],
                        'sales_date'        => $request['SALES_DATE'],
                        'fr_score'          => $request['FR_SCORE']
                    ]);

                DealsUser::where('id_deals_user', $idVoucher)->update(['paid_status' => 'Cancelled']);
            } catch (\Exception $e) {
                LogBackendError::logExceptionMessage("ApiTransactionCIMB/callbackDeals=>" . $e->getMessage(), $e);
                DB::rollback();
                return redirect('#transaction_fail');
            }

            DB::commit();

            $voucher = DealsUser::with(['userMid', 'dealVoucher'])->where('id_deals_user', $idVoucher)->first();
            if (\Module::collections()->has('Autocrm')) {
                $phone = User::where('id', $voucher->id_user)->pluck('phone')->first();
                $voucher->load('dealVoucher.deals');

                $autocrm = app($this->autocrm)->SendAutoCRM(
                    'Claim Paid Deals Cancelled',
                    $phone,
                    [
                        'claimed_at'                => $voucher->claimed_at,
                        'deals_title'               => $voucher->dealVoucher->deals->deals_title,
                        'id_deals_user'             => $idVoucher,
                        'deals_voucher_price_point' => (string) $voucher->voucher_price_point,
                        'id_deals'                  => $voucher->dealVoucher->deals->id_deals,
                        'id_brand'                  => $voucher->dealVoucher->deals->id_brand
                    ]
                );
            }

            return redirect('#transaction_fail');
        }
    }

    public function curlCimb(Request $request)
    {
        DB::beginTransaction();

        try {
            if (isset($request['deals']) && $request['deals'] == 1) {
                DealsPaymentCimb::create([
                    'amount'            => $request['AMOUNT'],
                    'merchant_tranid'   => $request['MERCHANT_TRANID']
                ]);
            } else {
                TransactionPaymentCimb::create([
                    'amount'            => $request['AMOUNT'],
                    'merchant_tranid'   => $request['MERCHANT_TRANID']
                ]);
            }
        } catch (\Exception $e) {
            LogBackendError::logExceptionMessage("ApiTransactionCIMB/curlCimb=>" . $e->getMessage(), $e);
            DB::rollback();
            return response()->json([
                'status'    => 'fail',
                'messages'  => [
                    'Something when wrong!. Contact Admin Support.'
                ]
            ]);
        }

        DB::commit();

        return view('transaction::curl_cimb', $request);
    }
}

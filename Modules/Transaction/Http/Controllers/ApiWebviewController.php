<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Transaction;
use App\Http\Models\Setting;
use App\Http\Models\DealsUser;
use App\Http\Models\TransactionPaymentManual;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\LogBalance;
use App\Http\Models\LogPoint;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsPaymentManual;

use App\Lib\MyHelper;

use Modules\Transaction\Http\Requests\TransactionDetail;

class ApiWebviewController extends Controller
{
    public function webview(TransactionDetail $request)
    {
        $id = $request->json('transaction_receipt_number');
        $type = $request->json('type');
        $check = $request->json('check');
        $button = '';

        $success = $request->json('trx_success');
        // return 'a';

        if (empty($check)) {
            // return 'b';
            if ($type == 'trx') {
                $list = Transaction::where('transaction_receipt_number', $id)->first();
                if (empty($list)) {
                    return response()->json(['status' => 'fail', 'messages' => ['Transaction not found']]);
                }

                $dataEncode = [
                    'transaction_receipt_number'   => $id,
                    'type' => $type,
                ];

                if (isset($success)) {
                    $dataEncode['trx_success'] = $success;
                    $button = 'LIHAT NOTA';
                }

                $encode = json_encode($dataEncode);
                $base = base64_encode($encode);

                $send = [
                    'status' => 'success',
                    'result' => [
                        'button'                     => $button,
                        'payment_status'             => $list['transaction_payment_status'],
                        'transaction_receipt_number' => $list['transaction_receipt_number'],
                        'transaction_grandtotal'     => $list['transaction_grandtotal'],
                        'type'                       => $type,
                        'url'                        => env('VIEW_URL').'/transaction/web/view/detail?data='.$base
                    ],
                ];

                return response()->json($send);
            } else {
                $list = $voucher = DealsUser::with('outlet', 'dealVoucher.deal')->where('id_deals_user', $id)->orderBy('claimed_at', 'DESC')->first();

                if (empty($list)) {
                    return response()->json(MyHelper::checkGet($list));
                }

                $dataEncode = [
                    'transaction_receipt_number'   => $id,
                    'type' => $type
                ];

                $encode = json_encode($dataEncode);
                $base = base64_encode($encode);
                // return $base;

                $send = [
                    'status'         => 'success',
                    'result'         => [
                        'payment_status'             => $list['paid_status'],
                        'transaction_receipt_number' => $list['id_deals_user'],
                        'transaction_grandtotal'     => $list['voucher_price_cash'],
                        'type'                       => $type,
                        'url'                        => env('VIEW_URL').'/transaction/web/view/detail?data='.$base
                    ],
                    
                ];

                return response()->json($send);
            }
        }

        if ($type == 'trx') {
            $list = Transaction::where('transaction_receipt_number', $id)->with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->first();
            $label = [];
            $label2 = [];

            $cart = $list['transaction_subtotal'] + $list['transaction_shipment'] + $list['transaction_service'] + $list['transaction_tax'] - $list['transaction_discount'];

            $list['transaction_carttotal'] = $cart;

            $order = Setting::where('key', 'transaction_grand_total_order')->value('value');
            $exp   = explode(',', $order);
            $exp2   = explode(',', $order);
            
            foreach ($exp as $i => $value) {
                if ($exp[$i] == 'subtotal') {
                    unset($exp[$i]);
                    unset($exp2[$i]);
                    continue;
                } 

                if ($exp[$i] == 'tax') {
                    $exp[$i] = 'transaction_tax';
                    $exp2[$i] = 'transaction_tax';
                    array_push($label, 'Tax');
                    array_push($label2, 'Tax');
                } 

                if ($exp[$i] == 'service') {
                    $exp[$i] = 'transaction_service';
                    $exp2[$i] = 'transaction_service';
                    array_push($label, 'Service Fee');
                    array_push($label2, 'Service Fee');
                } 

                if ($exp[$i] == 'shipping') {
                    if ($list['trasaction_type'] == 'Pickup Order') {
                        unset($exp[$i]);
                        unset($exp2[$i]);
                        continue;
                    } else {
                        $exp[$i] = 'transaction_shipment';
                        $exp2[$i] = 'transaction_shipment';
                        array_push($label, 'Delivery Cost');
                        array_push($label2, 'Delivery Cost');
                    }
                } 

                if ($exp[$i] == 'discount') {
                    $exp2[$i] = 'transaction_discount';
                    array_push($label2, 'Discount');
                    unset($exp[$i]);
                    continue;
                }

                if (stristr($exp[$i], 'empty')) {
                    unset($exp[$i]);
                    unset($exp2[$i]);
                    continue;
                } 
            }

            if ($list['trasaction_payment_type'] == 'Balance') {
                $log = LogBalance::where('id_reference', $list['id_transaction'])->first();
                if ($log['balance'] < 0) {
                    $list['balance'] = $log['balance'];
                    $list['check'] = 'tidak topup';
                } else {
                    $list['balance'] = $list['transaction_grandtotal'] - $log['balance'];
                    $list['check'] = 'topup';
                }
            }

            if ($list['trasaction_payment_type'] == 'Manual') {
                $payment = TransactionPaymentManual::with('manual_payment_method.manual_payment')->where('id_transaction', $list['id_transaction'])->first();
                $list['payment'] = $payment;
            }

            if ($list['trasaction_payment_type'] == 'Midtrans') {
                $payment = TransactionPaymentMidtran::where('id_transaction', $list['id_transaction'])->first();
                $list['payment'] = $payment;
            }

            if ($list['trasaction_payment_type'] == 'Offline') {
                $payment = TransactionPaymentOffline::where('id_transaction', $list['id_transaction'])->get();
                $list['payment_offline'] = $payment;
            }

            array_splice($exp, 0, 0, 'transaction_subtotal');
            array_splice($label, 0, 0, 'Cart Total');

            array_splice($exp2, 0, 0, 'transaction_subtotal');
            array_splice($label2, 0, 0, 'Cart Total');

            array_values($exp);
            array_values($label);

            array_values($exp2);
            array_values($label2);

            $imp = implode(',', $exp);
            $order_label = implode(',', $label);

            $imp2 = implode(',', $exp2);
            $order_label2 = implode(',', $label2);

            $detail = [];

            $qrTest= '';

            if ($list['trasaction_type'] == 'Pickup Order') {
                $detail = TransactionPickup::where('id_transaction', $list['id_transaction'])->first();
                $qrTest = $detail['order_id'];
            } elseif ($list['trasaction_type'] == 'Delivery') {
                $detail = TransactionShipment::with('city.province')->where('id_transaction', $list['id_transaction'])->first();
            }

            $list['detail'] = $detail;
            $list['order'] = $imp;
            $list['order_label'] = $order_label;

            $list['order_v2'] = $imp2;
            $list['order_label_v2'] = $order_label2;

            $list['date'] = $list['transaction_date'];
            $list['type'] = 'trx';

            $list['kind'] = $list['trasaction_type'];

            if (isset($success)) {
                $list['success'] = 1;
            
                $qrCode = 'https://chart.googleapis.com/chart?chl='.$qrTest.'&chs=250x250&cht=qr&chld=H%7C0';
                $qrCode = html_entity_decode($qrCode);
                $list['qr'] = $qrCode;
            }

            $settingService = Setting::where('key', 'service')->first();
            $settingTax = Setting::where('key', 'tax')->first();

            $list['valueService'] = 100 * $settingService['value'];
            $list['valueTax'] = 100 * $settingTax['value'];

            return response()->json(MyHelper::checkGet($list));
        } else {
            $list = $voucher = DealsUser::with('outlet', 'dealVoucher.deal')->where('id_deals_user', $id)->orderBy('claimed_at', 'DESC')->first();

            if (empty($list)) {
                return response()->json(MyHelper::checkGet($list));
            }

            if ($list['payment_method'] == 'Midtrans') {
                $payment = DealsPaymentMidtran::where('id_deals_user', $id)->first();
            } else {
                $payment = DealsPaymentManual::where('id_deals_user', $id)->first();
            }

            $list['payment'] = $payment;

            $list['date'] = $list['claimed_at'];
            $list['type'] = 'voucher';
            $list['kind'] = 'Voucher';

            return response()->json(MyHelper::checkGet($list));
        }
        
    }

    public function webviewPoint(Request $request)
    {
        $id     = $request->json('id');
        $select = [];
        $check = $request->json('check');
        $receipt = null;

        $data   = LogPoint::where('id_log_point', $id)->first();
        if ($data['source'] == 'Transaction') {
            $select = Transaction::with('outlet')->where('id_transaction', $data['id_reference'])->first();
            $receipt = $select['transaction_receipt_number']; 
            $type = 'trx';
        } else {
            $type = 'voucher';
        }

        if (empty($check)) {
            $dataEncode = [
                'id'   => $id
            ];

            $encode = json_encode($dataEncode);
            $base = base64_encode($encode);
            // return $base;

            $send = [
                'status'                     => 'success',
                'result' => [
                    'type'                       => $type,
                    'transaction_receipt_number' => $receipt,
                    'url'                        => env('VIEW_URL').'/transaction/web/view/detail/point?data='.$base
                ],
            ];

            return response()->json($send);
        }

        $data   = LogPoint::where('id_log_point', $id)->first();
        if ($data['source'] == 'Transaction') {
            $select = Transaction::with('outlet')->where('id_transaction', $data['id_reference'])->first();

            $data['date'] = $select['transaction_date'];
            $data['type'] = 'trx';
            $data['outlet'] = $select['outlet']['outlet_name'];
            if ($select['trasaction_type'] == 'Offline') {
                $data['online'] = 0;
            } else {
                $data['online'] = 1;
            }
            
        } else {
            $select = DealsUser::with('dealVoucher.deal')->where('id_deals_user', $data['id_reference'])->first();
            $data['type']   = 'voucher';
            $data['date']   = date('Y-m-d H:i:s', strtotime($select['claimed_at']));
            $data['outlet'] = $select['outlet']['outlet_name'];
            $data['online'] = 1;
        }

        $data['detail'] = $select;
        return response()->json(MyHelper::checkGet($data));
    }

    public function trxSuccess(Request $request)
    {
        $post = $request->json()->all();
        $check = Transaction::where('transaction_receipt_number', $post['id'])->first();
        if (empty($check)) {
            return response()->json([
                'status' => 'fail', 
                'messages' => ['Transaction not found']
            ]);
        }


    }
}
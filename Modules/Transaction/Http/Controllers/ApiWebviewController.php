<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\TransactionPaymentOvo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Transaction;
use App\Http\Models\Setting;
use App\Http\Models\DealsUser;
use App\Http\Models\TransactionPaymentManual;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\LogBalance;
use App\Http\Models\LogPoint;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsPaymentManual;

use App\Lib\MyHelper;

use Modules\IPay88\Entities\TransactionPaymentIpay88;
use Modules\Transaction\Http\Requests\TransactionDetail;

class ApiWebviewController extends Controller
{
    public function webview(TransactionDetail $request)
    {
        $id = $request->json('id_transaction');
        $type = $request->json('type');
        $check = $request->json('check');
        $button = '';
        $use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();

        $success = $request->json('trx_success');

        $user = $request->user();

        if (empty($check)) {
            if ($type == 'trx') {
                // if(count($arrId) != 2){
                //     $list = Transaction::where('transaction_receipt_number', $id)->first();
                // }else{
                $list = Transaction::where([['id_transaction', $id],['id_user', $user->id]])->first();
                // }

                if (empty($list)) {
                    return response()->json(['status' => 'fail', 'messages' => ['Transaction not found']]);
                }

                $dataEncode = [
                    'id_transaction'   => $id,
                    'type' => $type,
                ];

                if (isset($success)) {
                    $dataEncode['trx_success'] = $success;
                    $button = 'LIHAT NOTA';
                }

                $title = 'Transaction Detail';
                // if ($list['transaction_payment_status'] == 'Pending') {
                //     $title = 'Pending';
                // }

                // if ($list['transaction_payment_status'] == 'Paid') {
                //     $title = 'Paid';
                // }

                // if ($list['transaction_payment_status'] == 'Completed') {
                //     $title = 'Success';
                // }

                // if ($list['transaction_payment_status'] == 'Cancelled') {
                //     $title = 'Failed';
                // }

                $encode = json_encode($dataEncode);
                $base = base64_encode($encode);

                $send = [
                    'status' => 'success',
                    'result' => [
                        'button'                     => $button,
                        'title'                      => $title,
                        'payment_status'             => $list['transaction_payment_status'],
                        'id_transaction'             => $list['transaction_receipt_number'],
                        'transaction_grandtotal'     => $list['transaction_grandtotal'],
                        'type'                       => $type,
                        'url'                        => env('API_URL').'api/transaction/web/view/detail?data='.$base
                    ],
                ];

                return response()->json($send);
            } else {
                $list = $voucher = DealsUser::with('outlet', 'dealVoucher.deal')->where('id_deals_user', $id)->orderBy('claimed_at', 'DESC')->first();

                if (empty($list)) {
                    return response()->json(MyHelper::checkGet($list));
                }

                $dataEncode = [
                    'id_transaction'    => $id,
                    'type'              => $type
                ];

                $encode = json_encode($dataEncode);
                $base = base64_encode($encode);

                $send = [
                    'status'         => 'success',
                    'result'         => [
                        'payment_status'             => $list['paid_status'],
                        'id_transaction'             => $list['id_deals_user'],
                        'transaction_grandtotal'     => $list['voucher_price_cash'],
                        'type'                       => $type,
                        'url'                        => env('API_URL').'api/transaction/web/view/detail?data='.$base
                    ],

                ];

                return response()->json($send);
            }
        }

        if ($type == 'trx') {
            if($request->json('id_transaction')){
                if($use_product_variant){
                    $list = Transaction::where([['id_transaction', $id]])->with(
                            'user.city.province',
                            'productTransaction.product.product_group',
                            'productTransaction.product.product_variants',
                            'productTransaction.product.product_group.product_category',
                            'productTransaction.modifiers',
                            'productTransaction.product.product_photos',
                            'productTransaction.product.product_discounts',
                            'transaction_payment_offlines',
                            'modifiers',
                            'outlet.city')->first();
                }else{
                    $list = Transaction::where([['id_transaction', $id],
                        ['id_user',$request->user()->id]])->with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.modifiers', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city')->first();
                }
            }else{
                $arrId = explode(',',$id);

                if(count($arrId) != 2){
                    $list = Transaction::where('transaction_receipt_number', $id)->with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city', 'transaction_vouchers.deals_voucher')->first();
                }else{
                    $list = Transaction::where('transaction_receipt_number', $arrId[0])->where('id_transaction', $arrId[1])->with('user.city.province', 'productTransaction.product.product_category', 'productTransaction.product.product_photos', 'productTransaction.product.product_discounts', 'transaction_payment_offlines', 'outlet.city', 'transaction_vouchers.deals_voucher')->first();
                }
            }
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

            $dataPayment = [];

            if ($list['trasaction_payment_type'] == 'Offline') {
                $getPayment = TransactionPaymentOffline::where('id_transaction', $list['id_transaction'])->get();
                foreach($getPayment as $pay){
                    $pay['type'] = 'Offline';
                    array_push($dataPayment, $pay);
                }
            }else{
                $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                // return $multiPayment;
                if (isset($multiPayment)) {
                    foreach ($multiPayment as $key => $value) {
                        if ($value->type == 'Midtrans') {
                            $getPayment = TransactionPaymentMidtran::where('id_transaction', $list['id_transaction'])->first();
                            if (!empty($getPayment)) {
                                $dataPush = [
                                    'payment_method' => $getPayment['bank'],
                                    'nominal' => $getPayment['gross_amount']
                                ];
                                array_push($dataPayment, $dataPush);
                            }
                        } elseif ($value->type == 'Balance') {
                            $getPayment = TransactionPaymentBalance::where('id_transaction', $list['id_transaction'])->first();
                            if (!empty($getPayment)) {
                                $dataPush = [
                                    'payment_method' => 'MAXX Points',
                                    'nominal' => $getPayment['balance_nominal']
                                ];
                                array_push($dataPayment, $dataPush);
                            }
                        }elseif ($value->type == 'Ovo') {
                            $getPayment = TransactionPaymentOvo::where('id_transaction', $list['id_transaction'])->first();
                            if (!empty($getPayment)) {
                                $dataPush = [
                                    'payment_method' => 'Ovo',
                                    'nominal' => $getPayment['amount']
                                ];
                                array_push($dataPayment, $dataPush);
                            }
                        }elseif ($value->type == 'Ipay88') {
                            $getPayment = TransactionPaymentIpay88::where('id_transaction', $list['id_transaction'])->first();
                            if (!empty($getPayment)) {
                                $dataPush = [
                                    'payment_method' => $getPayment['payment_method'],
                                    'nominal' => $getPayment['amount']
                                ];
                                array_push($dataPayment, $dataPush);
                            }
                        }
                    }
                }else{
                    if($list['trasaction_payment_type'] == 'Midtrans') {
                        $getPayment = TransactionPaymentMidtran::where('id_transaction', $list['id_transaction'])->first();
                        if (!empty($getPayment)) {
                            $dataPush = [
                                'payment_method' => $getPayment['bank'],
                                'nominal' => $getPayment['gross_amount']
                            ];
                            array_push($dataPayment, $dataPush);
                        }
                    }

                    if ($list['trasaction_payment_type'] == 'Balance') {
                        $getPayment = TransactionPaymentBalance::where('id_transaction', $list['id_transaction'])->first();
                        if($getPayment){
                            if (!empty($getPayment)) {
                                $dataPush = [
                                    'payment_method' => 'MAXX Points',
                                    'nominal' => $getPayment['balance_nominal']
                                ];
                                array_push($dataPayment, $dataPush);
                            }
                        }
                    }
                }

            }

            // if ($list['trasaction_payment_type'] == 'Balance') {
            //     $log = LogBalance::where('id_reference', $list['id_transaction'])->where('source', 'Transaction')->where('balance', '<', 0)->first();
            //     if ($log['balance'] < 0) {
            //         $list['balance'] = $log['balance'];
            //         $list['check'] = 'tidak topup';
            //     } else {
            //         $list['balance'] = $list['transaction_grandtotal'] - $log['balance'];
            //         $list['check'] = 'topup';
            //     }
            // }

            // if ($list['trasaction_payment_type'] == 'Manual') {
            //     $payment = TransactionPaymentManual::with('manual_payment_method.manual_payment')->where('id_transaction', $list['id_transaction'])->first();
            //     $list['payment'] = $payment;
            // }

            // if ($list['trasaction_payment_type'] == 'Offline') {
            //     $payment = TransactionPaymentOffline::where('id_transaction', $list['id_transaction'])->get();
            //     $list['payment_offline'] = $payment;
            // }



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
                $detail = TransactionPickup::where('id_transaction', $list['id_transaction'])->with('transaction_pickup_go_send')->first();
                $qrTest = $detail['order_id'];
            } elseif ($list['trasaction_type'] == 'Delivery') {
                $detail = TransactionShipment::with('city.province')->where('id_transaction', $list['id_transaction'])->first();
            }

            $list['data_payment'] = $dataPayment;

            $list['detail'] = $detail;
            $list['order'] = $imp;
            $list['order_label'] = $order_label;

            $list['order_v2'] = $imp2;
            $list['order_label_v2'] = $order_label2;

            $list['date'] = $list['transaction_date'];
            $list['type'] = 'trx';

            $list['kind'] = $list['trasaction_type'];

            $statusPickup = "";
            if(isset($detail['reject_at']) && $detail['reject_at'] != null){
                $statusPickup  = 'Reject';
            }
            elseif(isset($detail['taken_at']) && $detail['taken_at'] != null){
                $statusPickup  = 'Taken';
            }
            elseif(isset($detail['ready_at']) && $detail['ready_at'] != null){
                $statusPickup  = 'Ready';
            }
            elseif(isset($detail['receive_at']) && $detail['receive_at'] != null){
                $statusPickup  = 'On Going';
            }
            else{
                $statusPickup  = 'Pending';
            }

            $list['status'] = $statusPickup;

            if (isset($success)) {
                $list['success'] = 1;

            }

            // $qrCode = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.$qrTest;
            $qrCode = 'https://chart.googleapis.com/chart?chl='.$qrTest.'&chs=250x250&cht=qr&chld=H%7C0';
            $qrCode = html_entity_decode($qrCode);
            $list['qr'] = $qrCode;

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

            $balance = LogBalance::where('id_reference', $id)->where('source', 'Deals Balance')->first();
            if($balance){
                $list['balance'] = $balance['balance'];
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
        if (empty($data)) {
            return response()->json(['status' => 'fail', 'messages' => ['Point not found']]);
        }

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
                    'url'                        => env('API_URL').'api/transaction/web/view/detail/point?data='.$base
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

    public function webviewBalance(Request $request)
    {
        $id     = $request->json('id');

        if (!isset($id)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data request is not valid']]);
        }

        $select = [];
        $check = $request->json('check');
        $receipt = null;

        $data   = LogBalance::where('id_log_balance', $id)->first();
        if ($data['source'] == 'Transaction' || $data['source'] == 'Rejected Order' || $data['source'] == 'Rejected Order Point' || $data['source'] == 'Rejected Order Midtrans' || $data['source'] == 'Reversal') {
            $select = Transaction::with('outlet')->where('id_transaction', $data['id_reference'])->first();
            $receipt = $select['transaction_receipt_number'];
            $type = 'trx';
        } else {
            $type = 'voucher';
        }

        if (empty($check)) {

            if($type == 'voucher'){
                $list = DealsUser::with('outlet', 'dealVoucher.deal')->where('id_deals_user', $data['id_reference'])->first();

                if ($list) {

                    $dataEncode = [
                        'id_transaction'   => $data['id_reference'],
                        'type' => $type
                    ];

                    $encode = json_encode($dataEncode);
                    $base = base64_encode($encode);

                    if($list['balance_nominal'] != null){
                        $list['voucher_price_cash'] = $list['voucher_price_cash'] - $list['balance_nominal'];
                    }

                    $send = [
                        'status'         => 'success',
                        'result'         => [
                            'payment_status'             => $list['paid_status'],
                            'id_transaction' => $list['id_deals_user'],
                            'transaction_grandtotal'     => $list['voucher_price_cash'],
                            'type'                       => $type,
                            'url'                        => env('API_URL').'api/transaction/web/view/detail?data='.$base
                        ],

                    ];

                    return response()->json($send);
                }
                return response()->json(['status' => 'fail', 'messages' => ['Data not valid']]);
            }
            $dataEncode2 = [
                'id_transaction'   => $select['id_transaction'],
                'type' => $type
            ];

            $encode2 = json_encode($dataEncode2);
            $base2 = base64_encode($encode2);

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
                    'id_transaction' => $select['id_transaction'],
                    'button'                     => 'View Detail',
                    'url'                        => env('API_URL').'api/transaction/web/view/detail/balance?data='.$base,
                    'trx_url'                    => env('API_URL').'api/transaction/web/view/detail?data='.$base2
                ],
            ];

            return response()->json($send);
        }

        $data   = LogBalance::where('id_log_balance', $id)->first();
        if ($data['source'] == 'Transaction' || $data['source'] == 'Rejected Order'  || $data['source'] == 'Rejected Order Point' || $data['source'] == 'Rejected Order Midtrans' || $data['source'] == 'Reversal') {
            $select = Transaction::with(['outlet', 'productTransaction.product'])->where('id_transaction', $data['id_reference'])->first();

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

    public function detail(Request $request)
    {
        $bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }

        $data = json_decode(base64_decode($request->get('data')), true);
        $data['check'] = 1;
        $check = MyHelper::postCURLWithBearer('api/transaction/detail/webview?log_save=0', $data, $bearer);

        if (isset($check['status']) && $check['status'] == 'success') {
            $data = $check['result'];
        } elseif (isset($check['status']) && $check['status'] == 'fail') {
            return view('error', ['msg' => 'Data failed']);
        } else {
            return view('error', ['msg' => 'Something went wrong, try again']);
        }

        if ($data['kind'] == 'Delivery') {
            $view = 'detail_transaction_deliv';
        }

        if ($data['kind'] == 'Pickup Order' || $data['kind'] == 'Offline') {
            $view = 'detail_transaction_pickup';
        }

        // 	if ($data['kind'] == 'Offline') {
        // 		$view = 'detail_transaction_off';
        // 	}

        if ($data['kind'] == 'Voucher') {
            $view = 'detail_transaction_voucher';
        }

        // if (isset($data['success'])) {
        //     $view = 'transaction_success';
        // }

        if (isset($data['transaction_payment_status']) && $data['transaction_payment_status'] == 'Pending') {
            $view = 'transaction_proccess';
            // if (isset($data['data_payment'])) {
            //     foreach ($data['data_payment'] as $key => $value) {
            //         if ($value['type'] != 'Midtrans') {
            //             continue;
            //         } else {
            //             if (!isset($value['signature_key'])) {
            //                 $view = 'transaction_pending';
            //             }
            //         }
            //     }
            // }
        }

        if (isset($data['transaction_payment_status']) && $data['transaction_payment_status'] == 'Cancelled') {
            $view = 'detail_transaction_pickup';
        }

        if (isset($data['order_label_v2'])) {
            $data['order_label_v2'] = explode(',', $data['order_label_v2']);
            $data['order_v2'] = explode(',', $data['order_v2']);
        }
        // dd($view);
        return view('transaction::webview.' . $view . '')->with(compact('data'));
    }

    public function detailTrx(Request $request,$receipt)
    {
        $bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }

        $data['id_transaction'] = $receipt;
        // $data['transaction_receipt_number'] = $receipt;
        $data['type'] = 'trx';
        $data['check'] = 1;
        $check = MyHelper::postCURLWithBearer('api/transaction/detail/webview?log_save=0', $data, $bearer);
        if (isset($check['status']) && $check['status'] == 'success') {
            $data = $check['result'];
        } elseif (isset($check['status']) && $check['status'] == 'fail') {
            return view('error', ['msg' => 'Data failed']);
        } else {
            return view('error', ['msg' => 'Something went wrong, try again']);
        }

        if ($data['kind'] == 'Delivery') {
            $view = 'detail_transaction_deliv';
        }

        if ($data['kind'] == 'Pickup Order' || $data['kind'] == 'Offline') {
            $view = 'detail_transaction_pickup';
        }

        //  if ($data['kind'] == 'Offline') {
        //      $view = 'detail_transaction_off';
        //  }

        if ($data['kind'] == 'Voucher') {
            $view = 'detail_transaction_voucher';
        }

        // if (isset($data['success'])) {
        //     $view = 'transaction_success';
        // }

        if (isset($data['transaction_payment_status']) && $data['transaction_payment_status'] == 'Pending') {
            $view = 'transaction_proccess';
            // if (isset($data['data_payment'])) {
            //     foreach ($data['data_payment'] as $key => $value) {
            //         if ($value['type'] != 'Midtrans') {
            //             continue;
            //         } else {
            //             if (!isset($value['signature_key'])) {
            //                 $view = 'transaction_pending';
            //             }
            //         }
            //     }
            // }
        }

        if (isset($data['transaction_payment_status']) && $data['transaction_payment_status'] == 'Cancelled') {
            $view = 'detail_transaction_pickup';
        }

        if (isset($data['order_label_v2'])) {
            $data['order_label_v2'] = explode(',', $data['order_label_v2']);
            $data['order_v2'] = explode(',', $data['order_v2']);
        }
        // dd($data);
        return view('transaction::webview.' . $view . '')->with(compact('data'));
    }

    public function outletSuccess(Request $request)
    {
        $bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }

        $data = json_decode(base64_decode($request->get('data')), true);
        $data['check'] = 1;
        $check = MyHelper::postCURLWithBearer('api/outletapp/order/detail/view?log_save=0', $data, $bearer);
        if (isset($check['status']) && $check['status'] == 'success') {
            $data = $check['result'];
        } elseif (isset($check['status']) && $check['status'] == 'fail') {
            return view('error', ['msg' => 'Data failed']);
        } else {
            return view('error', ['msg' => 'Something went wrong, try again']);
        }

        if (isset($data['order_label_v2'])) {
            $data['order_label_v2'] = explode(',', $data['order_label_v2']);
            $data['order_v2'] = explode(',', $data['order_v2']);
        }
        return view('transaction::webview.outlet_app')->with(compact('data'));
    }

    public function detailPoint(Request $request)
    {
        $bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }

        $data = json_decode(base64_decode($request->get('data')), true);
        $data['check'] = 1;
        $check = MyHelper::postCURLWithBearer('api/transaction/detail/webview/point?log_save=0', $data, $bearer);

        if (isset($check['status']) && $check['status'] == 'success') {
            $data = $check['result'];
        } elseif (isset($check['status']) && $check['status'] == 'fail') {
            return view('error', ['msg' => 'Data failed']);
        } else {
            return view('error', ['msg' => 'Something went wrong, try again']);
        }

        if ($data['type'] == 'trx') {
            $view = 'detail_point_online';
        }

        if ($data['type'] == 'voucher') {
            $view = 'detail_point_voucher';
        }

        return view('transaction::webview.' . $view . '')->with(compact('data'));
    }

    public function detailBalance(Request $request)
    {
        $bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }

        $data = json_decode(base64_decode($request->get('data')), true);
        $data['check'] = 1;
        $check = MyHelper::postCURLWithBearer('api/transaction/detail/webview/balance?log_save=0', $data, $bearer);

        if (isset($check['status']) && $check['status'] == 'success') {
            $data = $check['result'];
        } elseif (isset($check['status']) && $check['status'] == 'fail') {
            return view('error', ['msg' => 'Data failed']);
        } else {
            return view('error', ['msg' => 'Something went wrong, try again']);
        }

        if ($data['type'] == 'trx') {
            $view = 'detail_balance_online';
        }

        if ($data['type'] == 'voucher') {
            $view = 'detail_balance_voucher';
        }
        // dd($data);
        return view('transaction::webview.' . $view . '')->with(compact('data'));
    }

    public function success()
    {
        return view('transaction::webview.transaction_success');
    }

    public function receiptOutletapp(Request $request)
    {
        $bearer = $request->header('Authorization');
        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        // if ($request->isMethod('get')) {
        //     return view('error', ['msg' => 'Url method is POST']);
        // }

        $data = json_decode(base64_decode($request->get('data')), true);
        $check = MyHelper::postCURLWithBearer('api/outletapp/order/detail/view?log_save=0', $data, $bearer);

        if (isset($check['status']) && $check['status'] == 'success') {
            $data = $check['result'];
        } elseif (isset($check['status']) && $check['status'] == 'fail') {
            return view('error', ['msg' => 'Data failed']);
        } else {
            return view('error', ['msg' => 'Something went wrong, try again']);
        }

        // return $data;

        return view('transaction::webview.receipt-outletapp')->with(compact('data'));
    }
}

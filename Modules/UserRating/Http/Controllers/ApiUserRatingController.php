<?php

namespace Modules\UserRating\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Outlet;
use App\Http\Models\Setting;
use App\Http\Models\Transaction;
use Modules\UserRating\Entities\UserRating;
use Modules\UserRating\Entities\RatingOption;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentOvo;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use App\Http\Models\TransactionPaymentManual;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionMultiplePayment;

use App\Lib\MyHelper;

use Modules\UserRating\Entities\UserRatingLog;

class ApiUserRatingController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $post = $request->json()->all();
        $data = UserRating::with(['transaction'=>function($query){
            $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal','id_outlet');
        },'transaction.outlet'=>function($query){
            $query->select('id_outlet','outlet_code','outlet_name');
        },'user'=>function($query){
            $query->select('id','name','phone');
        }])->orderBy('id_user_rating','desc');

        // if($outlet_code = ($request['outlet_code']??false)){
        //     $data->whereHas('transaction.outlet',function($query) use ($outlet_code){
        //         $query->where('outlet_code',$outlet_code);
        //     });
        // }

        if($post['rule']??false){
            $this->filterList($data,$post['rule'],$post['operator']??'and');
        }

        $data= $data->paginate(10)->toArray();
        return MyHelper::checkGet($data);
    }

    public function filterList($model,$rule,$operator='and'){
        $newRule=[];
        $where=$operator=='and'?'where':'orWhere';
        foreach ($rule as $var) {
            $var1=['operator'=>$var['operator']??'=','parameter'=>$var['parameter']??null];
            if($var1['operator']=='like'){
                $var1['parameter']='%'.$var1['parameter'].'%';
            }
            $newRule[$var['subject']][]=$var1;
        }
        if($rules=$newRule['review_date']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Date'}('created_at',$rul['operator'],$rul['parameter']);
            }
        }
        if($rules=$newRule['star']??false){
            foreach ($rules as $rul) {
                $model->$where('rating_value',$rul['operator'],$rul['parameter']);
            }
        }
        if($rules=$newRule['transaction_date']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction',function($query) use ($rul){
                    $query->whereDate('transaction_date',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['transaction_type']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction',function($query) use ($rul){
                    $query->where('transaction_type',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['transaction_receipt_number']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction',function($query) use ($rul){
                    $query->where('transaction_receipt_number',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['user_name']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('user',function($query) use ($rul){
                    $query->where('name',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['user_phone']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('user',function($query) use ($rul){
                    $query->where('phone',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['user_email']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('user',function($query) use ($rul){
                    $query->where('email',$rul['operator'],$rul['parameter']);
                });
            }
        }
        if($rules=$newRule['outlet']??false){
            foreach ($rules as $rul) {
                $model->{$where.'Has'}('transaction.outlet',function($query) use ($rul){
                    $query->where('id_outlet',$rul['operator'],$rul['parameter']);
                });
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $post = $request->json()->all();
        $id = $post['id'];
        $user = $request->user();
        $trx = Transaction::where([
            'id_transaction'=>$id,
            'id_user'=>$request->user()->id
        ])->first();
        if(!$trx){
            return [
                'status'=>'fail',
                'messages'=>['Transaction not found']
            ];
        }
        $max_rating_value = Setting::select('value')->where('key','response_max_rating_value')->pluck('value')->first()?:2;
        if($post['rating_value'] <= $max_rating_value){
            $trx->load('outlet_name');
            $variables = [
                'receipt_number' => $trx->transaction_receipt_number,
                'outlet_name' => $trx->outlet_name->outlet_name,
                'transaction_date' => date('d F Y H:i',strtotime($trx->transaction_date)),
                'rating_value' => (string) $post['rating_value'],
                'suggestion' => $post['suggestion']??'',
                'question' => $post['option_question'],
                'selected_option' => implode(',',array_map(function($var){return trim($var,'"');},$post['option_value']??[]))
            ];
            app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('User Rating', $user->phone, $variables,null,true);
        }
        $insert = [
            'id_transaction' => $trx->id_transaction,
            'id_user' => $request->user()->id,
            'rating_value' => $post['rating_value'],
            'suggestion' => $post['suggestion']??'',
            'option_question' => $post['option_question'],
            'option_value' => implode(',',array_map(function($var){return trim($var,'"');},$post['option_value']??[]))
        ];
        $create = UserRating::updateOrCreate(['id_transaction'=>$trx->id_transaction],$insert);
        UserRatingLog::where('id_user',$request->user()->id)->delete();
        if($create){
            Transaction::where('id_user',$user->id)->update(['show_rate_popup'=>0]);
        }
        return MyHelper::checkCreate($create);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $post = $request->json()->all();
        $data = UserRating::with(['transaction'=>function($query){
            $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal','id_outlet');
        },'transaction.outlet'=>function($query){
            $query->select('id_outlet','outlet_code','outlet_name');
        },'user'=>function($query){
            $query->select('id','name','phone');
        }])->where([
            'id_user_rating'=>$post['id']
        ])->first();
        return MyHelper::checkGet($data);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        return MyHelper::checkDelete(UserRating::find($request->json('id_user_rating'))->delete());
    }
    
    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function getDetail(Request $request) {
        $post = $request->json()->all();
        // rating item
        $use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();
        $user = $request->user();
        if($post['id']??false){
            $id_transaction = $post['id'];
            $rn = $id_trx[0]??'';
            if($use_product_variant){
                $transaction = Transaction::where([['id_transaction', $id_transaction],
                ['id_user',$user->id]])->with(
                    // 'user.city.province',
                    'productTransaction.product.product_group',
                    'productTransaction.product.product_variants',
                    'productTransaction.product.product_group.product_category',
                    'productTransaction.modifiers',
                    'productTransaction.product.product_photos',
                    'productTransaction.product.product_discounts',
                    'transaction_payment_offlines',
                    'transaction_vouchers.deals_voucher.deal',
                    'promo_campaign_promo_code.promo_campaign',
                    'outlet.city')
                    ->first();
            }else{
                $transaction = Transaction::where([['id_transaction', $id_transaction],
                ['id_user',$user->id]])->with(
                    // 'user.city.province',
                    'productTransaction.product.product_category',
                    'productTransaction.modifiers',
                    'productTransaction.product.product_photos',
                    'productTransaction.product.product_discounts',
                    'transaction_payment_offlines',
                    'transaction_vouchers.deals_voucher.deal',
                    'promo_campaign_promo_code.promo_campaign',
                    'outlet.city')
                    ->first();
            }
            if(!$transaction){
                return [
                    'status' => 'fail',
                    'messages' => ['Transaction not found']
                ];
            }
        }else{
            $user->load('log_popup');
            $log_popup = $user->log_popup;
            if($log_popup){
                $interval =(Setting::where('key','popup_min_interval')->pluck('value')->first()?:900);
                if(
                    $log_popup->refuse_count>=(Setting::where('key','popup_max_refuse')->pluck('value')->first()?:3) ||
                    strtotime($log_popup->last_popup)+$interval>time()
                ){
                    return MyHelper::checkGet([]);
                }
                $log_popup->refuse_count++;
                $log_popup->last_popup = date('Y-m-d H:i:s');
                $log_popup->save();
            }else{
                UserRatingLog::create([
                    'id_user' => $user->id,
                    'refuse_count' => 1,
                    'last_popup' => date('Y-m-d H:i:s')
                ]);
            }
            if($use_product_variant){
                $transaction = Transaction::where(['show_rate_popup'=>1,'id_user'=>$user->id])->with(
                    // 'user.city.province',
                    'productTransaction.product.product_group',
                    'productTransaction.product.product_variants',
                    'productTransaction.product.product_group.product_category',
                    'productTransaction.modifiers',
                    'productTransaction.product.product_photos',
                    'productTransaction.product.product_discounts',
                    'transaction_payment_offlines',
                    'transaction_vouchers.deals_voucher.deal',
                    'promo_campaign_promo_code.promo_campaign',
                    'outlet.city')
                    ->first();
            }else{
                $transaction = Transaction::where(['show_rate_popup'=>1,'id_user'=>$user->id])->with(
                    // 'user.city.province',
                    'productTransaction.product.product_category',
                    'productTransaction.modifiers',
                    'productTransaction.product.product_photos',
                    'productTransaction.product.product_discounts',
                    'transaction_payment_offlines',
                    'transaction_vouchers.deals_voucher.deal',
                    'promo_campaign_promo_code.promo_campaign',
                    'outlet.city')
                    ->first();
            }
            if(!$transaction){
                return MyHelper::checkGet([]);
            }
        }
        $result['id'] = $transaction->id_transaction;
        $result['transaction_receipt_number'] = $transaction->transaction_receipt_number;
        $result['question_text'] = Setting::where('key','rating_question_text')->pluck('value_text')->first()?:'How about our Service';
        $result['transaction_date'] = date('d M Y H:i',strtotime($transaction->transaction_date));
        $defaultOptions = [
            'question'=>Setting::where('key','default_rating_question')->pluck('value_text')->first()?:'What\'s best from us?',
            'options' =>explode(',',Setting::where('key','default_rating_options')->pluck('value_text')->first()?:'Cleanness,Accuracy,Employee Hospitality,Process Time')
        ];
        $options = ['1'=>$defaultOptions,'2'=>$defaultOptions,'3'=>$defaultOptions,'4'=>$defaultOptions,'5'=>$defaultOptions];
        $ratings = RatingOption::select('star','question','options')->get();
        foreach ($ratings as $rt) {
            $stars = explode(',',$rt['star']);
            foreach ($stars as $star) {
                $options[$star] = [
                    'question'=>$rt['question'],
                    'options'=>explode(',',$rt['options'])
                ];
            }
        }
        $result['options'] = $options;

        $list = $transaction->toArray();
        $label = [];
        $label2 = [];
        $product_count=0;
        if(!$use_product_variant){
            $list['product_transaction'] = MyHelper::groupIt($list['product_transaction'],'id_brand',null,function($key,&$val) use (&$product_count){
                $product_count += array_sum(array_column($val,'transaction_product_qty'));
                $brand = Brand::select('name_brand')->find($key);
                if(!$brand){
                    return 'No Brand';
                }
                return $brand->name_brand;
            });
        }
        $cart = $list['transaction_subtotal'] + $list['transaction_shipment'] + $list['transaction_service'] + $list['transaction_tax'] - $list['transaction_discount'];

        $list['transaction_carttotal'] = $cart;
        $list['transaction_item_total'] = $product_count;

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

        switch ($list['trasaction_payment_type']) {
            case 'Balance':
                $log = LogBalance::where('id_reference', $list['id_transaction'])->first();
                if ($log['balance'] < 0) {
                    $list['balance'] = $log['balance'];
                    $list['check'] = 'tidak topup';
                } else {
                    $list['balance'] = $list['transaction_grandtotal'] - $log['balance'];
                    $list['check'] = 'topup';
                }
                $list['payment'][] = [
                    'name'      => 'Balance',
                    'amount'    => $list['balance']
                ];
                break;
            case 'Manual':
                $payment = TransactionPaymentManual::with('manual_payment_method.manual_payment')->where('id_transaction', $list['id_transaction'])->first();
                $list['payment'] = $payment;
                $list['payment'][] = [
                    'name'      => 'Cash',
                    'amount'    => $payment['payment_nominal']
                ];
                break;
            case 'Midtrans':
                $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                $payment = [];
                foreach($multiPayment as $dataKey => $dataPay){
                    if($dataPay['type'] == 'Midtrans'){
                        $payment[$dataKey]['name']      = 'Midtrans';
                        $payment[$dataKey]['amount']    = TransactionPaymentMidtran::find($dataPay['id_payment'])->gross_amount;
                    }else{
                        $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                        $payment[$dataKey] = $dataPay;
                        $list['balance'] = $dataPay['balance_nominal'];
                        $payment[$dataKey]['name']          = 'Balance';
                        $payment[$dataKey]['amount']        = $dataPay['balance_nominal'];
                    }
                }
                $list['payment'] = $payment;
                break;
            case 'Ovo':
                $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                $payment = [];
                foreach($multiPayment as $dataKey => $dataPay){
                    if($dataPay['type'] == 'Ovo'){
                        $payment[$dataKey] = TransactionPaymentOvo::find($dataPay['id_payment']);
                        $payment[$dataKey]['name']    = 'OVO';
                    }else{
                        $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                        $payment[$dataKey] = $dataPay;
                        $list['balance'] = $dataPay['balance_nominal'];
                        $payment[$dataKey]['name']          = 'Balance';
                        $payment[$dataKey]['amount']        = $dataPay['balance_nominal'];
                    }
                }
                $list['payment'] = $payment;
                break;
            case 'Ipay88':
                $multiPayment = TransactionMultiplePayment::where('id_transaction', $list['id_transaction'])->get();
                $payment = [];
                foreach($multiPayment as $dataKey => $dataPay){
                    if($dataPay['type'] == 'IPay88'){
                        $payment[$dataKey]['name']    = 'Ipay88';
                        $payment[$dataKey]['amount']    = TransactionPaymentIpay88::find($dataPay['id_payment'])->amount / 100;
                    }else{
                        $dataPay = TransactionPaymentBalance::find($dataPay['id_payment']);
                        $payment[$dataKey] = $dataPay;
                        $list['balance'] = $dataPay['balance_nominal'];
                        $payment[$dataKey]['name']          = 'Balance';
                        $payment[$dataKey]['amount']        = $dataPay['balance_nominal'];
                    }
                }
                $list['payment'] = $payment;
                break;
            case 'Offline':
                $payment = TransactionPaymentOffline::where('id_transaction', $list['id_transaction'])->get();
                foreach ($payment as $key => $value) {
                    $list['payment'][$key] = [
                        'name'      => $value['payment_bank'],
                        'amount'    => $value['payment_amount']
                    ];
                }
                break;
            default:
                $list['payment'][] = [
                    'name'      => null,
                    'amount'    => null
                ];
                break;
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

        if ($list['trasaction_type'] == 'Pickup Order') {
            $detail = TransactionPickup::where('id_transaction', $list['id_transaction'])->first()->toArray();
            if($detail){
                $qr      = $detail['order_id'].strtotime($list['transaction_date']);

                $qrCode = 'https://chart.googleapis.com/chart?chl='.$qr.'&chs=250x250&cht=qr&chld=H%7C0';
                $qrCode =   html_entity_decode($qrCode);

                $newDetail = [];
                foreach($detail as $key => $value){
                    $newDetail[$key] = $value;
                    if($key == 'order_id'){
                        $newDetail['order_id_qrcode'] = $qrCode;
                    }
                }

                $detail = $newDetail;
            }
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

        $result['detail_trx'] = [
            'id_transaction'                => $list['id_transaction'],
            'transaction_receipt_number'    => $list['transaction_receipt_number'],
            'transaction_date'              => date('d M Y H:i', strtotime($list['transaction_date'])),
            'trasaction_type'               => $list['trasaction_type'],
            'transaction_grandtotal'        => MyHelper::requestNumber($list['transaction_grandtotal'],'_CURRENCY'),
            'transaction_subtotal'          => MyHelper::requestNumber($list['transaction_subtotal'],'_CURRENCY'),
            'transaction_discount'          => MyHelper::requestNumber($list['transaction_discount'],'_CURRENCY'),
            'transaction_cashback_earned'   => MyHelper::requestNumber($list['transaction_cashback_earned'],'_POINT'),
            'trasaction_payment_type'       => $list['trasaction_payment_type'],
            'transaction_payment_status'    => $list['transaction_payment_status'],
            'outlet'                        => [
                'outlet_name'       => $list['outlet']['outlet_name'],
                'outlet_address'    => $list['outlet']['outlet_address']
            ]
        ];

        if ($list['trasaction_payment_type'] != 'Offline') {
            $result['detail_trx']['detail'] = [
                    'order_id_qrcode'   => $list['detail']['order_id_qrcode'],
                    'order_id'          => $list['detail']['order_id'],
                    'pickup_type'       => $list['detail']['pickup_type'],
                    'pickup_date'       => date('d F Y', strtotime($list['detail']['pickup_at'])),
                    'pickup_time'       => ($list['detail']['pickup_type'] == 'right now') ? 'RIGHT NOW' : date('H : i', strtotime($list['detail']['pickup_at'])),
            ];
            if (isset($list['transaction_payment_status']) && $list['transaction_payment_status'] == 'Cancelled') {
                $result['detail_trx']['transaction_status'] = 'Order Canceled';
            } elseif($list['detail']['reject_at'] != null) {
                $result['detail_trx']['transaction_status'] = 'Order Rejected';
            } elseif($list['detail']['taken_by_system_at'] != null) {
                $result['detail_trx']['transaction_status'] = 'Order Has Been Done';
            } elseif($list['detail']['taken_at'] != null) {
                $result['detail_trx']['transaction_status'] = 'Order Has Been Taken';
            } elseif($list['detail']['ready_at'] != null) {
                $result['detail_trx']['transaction_status'] = 'Order Is Ready';
            } elseif($list['detail']['receive_at'] != null) {
                $result['detail_trx']['transaction_status'] = 'Order Received';
            } else {
                $result['detail_trx']['transaction_status'] = 'Order Pending';
            }
        }

        $discount = 0;
        $quantity = 0;
        foreach ($list['product_transaction'] as $keyTrx => $valueTrx) {
            $quantity = $quantity + $valueTrx['transaction_product_qty'];
            $result['detail_trx']['product_transaction'][$keyTrx]['transaction_product_qty']              = $valueTrx['transaction_product_qty'];
            $result['detail_trx']['product_transaction'][$keyTrx]['transaction_product_subtotal']         = MyHelper::requestNumber($valueTrx['transaction_product_subtotal'],'_CURRENCY');
            $result['detail_trx']['product_transaction'][$keyTrx]['transaction_product_sub_item']         = '@'.MyHelper::requestNumber($valueTrx['transaction_product_subtotal'] / $valueTrx['transaction_product_qty'],'_CURRENCY');
            $result['detail_trx']['product_transaction'][$keyTrx]['transaction_modifier_subtotal']        = MyHelper::requestNumber($valueTrx['transaction_modifier_subtotal'],'_CURRENCY');
            $result['detail_trx']['product_transaction'][$keyTrx]['transaction_product_note']             = $valueTrx['transaction_product_note'];
            $result['detail_trx']['product_transaction'][$keyTrx]['product']['product_name']              = $valueTrx['product']['product_name'];
            $discount = $discount + $valueTrx['transaction_product_discount'];
            foreach ($valueTrx['product']['product_variants'] as $keyVar => $valueVar) {
                $result['detail_trx']['product_transaction'][$keyTrx]['product']['product_variants'][$keyVar]['product_variant_name']     = $valueVar['product_variant_name'];
            }
            foreach ($valueTrx['modifiers'] as $keyMod => $valueMod) {
                $result['detail_trx']['product_transaction'][$keyTrx]['product']['product_modifiers'][$keyMod]['product_modifier_name']   = $valueMod['text'];
                $result['detail_trx']['product_transaction'][$keyTrx]['product']['product_modifiers'][$keyMod]['product_modifier_qty']    = $valueMod['qty'];
                $result['detail_trx']['product_transaction'][$keyTrx]['product']['product_modifiers'][$keyMod]['product_modifier_price']  = MyHelper::requestNumber($valueMod['transaction_product_modifier_price'],'_CURRENCY');
            }
        }

        $result['detail_trx']['payment_detail'][] = [
            'name'      => 'Subtotal',
            'desc'      => $quantity . ' items',
            'amount'    => MyHelper::requestNumber($list['transaction_subtotal'],'_CURRENCY')
        ];

        $p = 0;
        if (!empty($list['transaction_vouchers'])) {
            foreach ($list['transaction_vouchers'] as $valueVoc) {
                $result['detail_trx']['promo']['code'][$p++]   = $valueVoc['deals_voucher']['voucher_code'];
                $result['detail_trx']['payment_detail'][] = [
                    'name'          => 'Discount',
                    'desc'          => $valueVoc['deals_voucher']['voucher_code'],
                    "is_discount"   => 1,
                    'amount'        => MyHelper::requestNumber($discount,'_CURRENCY')
                ];
            }
        }

        if (!empty($list['promo_campaign_promo_code'])) {
            $result['detail_trx']['promo']['code'][$p++]   = $list['promo_campaign_promo_code']['promo_code'];
            $result['detail_trx']['payment_detail'][] = [
                'name'          => 'Discount',
                'desc'          => $list['promo_campaign_promo_code']['promo_code'],
                "is_discount"   => 1,
                'amount'        => MyHelper::requestNumber($discount,'_CURRENCY')
            ];
        }

        $result['detail_trx']['promo']['discount'] = $discount;
        $result['detail_trx']['promo']['discount'] = MyHelper::requestNumber($discount,'_CURRENCY');

        if ($list['trasaction_payment_type'] != 'Offline') {
            if ($list['transaction_payment_status'] == 'Cancelled') {
                $result['detail_trx']['detail']['detail_status'][] = [
                'text'  => 'Your order has been canceled',
                'date'  => date('d F Y H:i', strtotime($list['void_date']))
            ];
            }
            if ($list['detail']['reject_at'] != null) {
                $result['detail_trx']['detail']['detail_status'][] = [
                'text'  => 'Order rejected',
                'date'  => date('d F Y H:i', strtotime($list['detail']['reject_at'])),
                'reason'=> $result['detail_trx']['detail']['reject_reason']
            ];
            }
            if ($list['detail']['taken_by_system_at'] != null) {
                $result['detail_trx']['detail']['detail_status'][] = [
                'text'  => 'Your order has been done by system',
                'date'  => date('d F Y H:i', strtotime($list['detail']['taken_by_system_at']))
            ];
            }
            if ($list['detail']['taken_at'] != null) {
                $result['detail_trx']['detail']['detail_status'][] = [
                'text'  => 'Your order has been taken',
                'date'  => date('d F Y H:i', strtotime($list['detail']['taken_at']))
            ];
            }
            if ($list['detail']['ready_at'] != null) {
                $result['detail_trx']['detail']['detail_status'][] = [
                'text'  => 'Your order is ready ',
                'date'  => date('d F Y H:i', strtotime($list['detail']['ready_at']))
            ];
            }
            if ($list['detail']['receive_at'] != null) {
                $result['detail_trx']['detail']['detail_status'][] = [
                'text'  => 'Your order has been received',
                'date'  => date('d F Y H:i', strtotime($list['detail']['receive_at']))
            ];
            }
            $result['detail_trx']['detail']['detail_status'][] = [
                'text'  => 'Your order awaits confirmation ',
                'date'  => date('d F Y H:i', strtotime($list['transaction_date']))
            ];
        }

        foreach ($list['payment'] as $key => $value) {
            if ($value['name'] == 'Balance') {
                $result['detail_trx']['transaction_payment'][$key] = [
                    'name'      => (env('POINT_NAME')) ? env('POINT_NAME') : $value['name'],
                    'is_balance'=> 1,
                    'amount'    => MyHelper::requestNumber($value['amount'],'_POINT')
                ];
            } else {
                $result['detail_trx']['transaction_payment'][$key] = [
                    'name'      => $value['name'],
                    'amount'    => MyHelper::requestNumber($value['amount'],'_CURRENCY')
                ];
            }
        }
        // $result['webview_url'] = env('APP_API_URL').'api/transaction/web/view/trx/'.$result['id'];
        return MyHelper::checkGet($result);
    }
    
    public function report(Request $request) {
        $post = $request->json()->all();
        $showOutlet = 10;
        $counter = UserRating::select(\DB::raw('rating_value,count(id_user_rating) as total'))
        ->groupBy('rating_value');
        $this->applyFilter($counter,$post);
        $counter = $counter->get()->toArray();
        foreach ($counter as &$value) {
            $datax = UserRating::where('rating_value',$value['rating_value'])->with([
                'transaction'=>function($query){
                    $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal');
                },'user'=>function($query){
                    $query->select('id','name','phone');
                }
            ])->take(10);
            $this->applyFilter($datax,$post);
            $value['data'] = $datax->get();
        }
        $outlet5 = UserRating::select(\DB::raw('outlets.id_outlet,outlet_name,outlet_code,user_ratings.rating_value,count(*) as total'))
        ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
        ->join('outlets','transactions.id_outlet','=','outlets.id_outlet')
        ->where('rating_value','5')
        ->groupBy('outlets.id_outlet')
        ->orderBy('total','desc')
        ->take($showOutlet);
        $this->applyFilter($outlet5,$post);
        for ($i=4; $i > 0 ; $i--) { 
            $outlet = UserRating::select(\DB::raw('outlets.id_outlet,outlet_name,outlet_code,user_ratings.rating_value,count(*) as total'))
            ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
            ->join('outlets','transactions.id_outlet','=','outlets.id_outlet')
            ->where('rating_value',$i)
            ->groupBy('outlets.id_outlet')
            ->orderBy('total','desc')
            ->take($showOutlet);
            $this->applyFilter($outlet,$post);
            $outlet5->union($outlet);
        }
        $data['rating_item'] = $counter;
        $data['rating_item_count'] = count($counter);
        $data['outlet_data'] = $outlet5->get();
        return MyHelper::checkGet($data);
    }
    // apply filter photos only/notes_only
    public function applyFilter($model,$rule,$col='user_ratings'){
        if($rule['notes_only']??false){
            $model->whereNotNull($col.'.suggestion');
            $model->where($col.'.suggestion','<>','');
        }
        $model->whereDate($col.'.created_at','>=',$rule['date_start'])->whereDate($col.'.created_at','<=',$rule['date_end']);
    }
    public function reportOutlet(Request $request) {
        $post = $request->json()->all();
        if($post['outlet_code']??false){
            $outlet = Outlet::select(\DB::raw('outlets.id_outlet,outlets.outlet_code,outlets.outlet_name,count(f1.id_user_rating) as rating1,count(f2.id_user_rating) as rating2,count(f3.id_user_rating) as rating3,count(f4.id_user_rating) as rating4,count(f5.id_user_rating) as rating5'))
            ->where('outlet_code',$post['outlet_code'])->join('transactions','outlets.id_outlet','=','transactions.id_outlet')
            ->leftJoin('user_ratings as f1',function($join) use ($post){
                $join->on('f1.id_transaction','=','transactions.id_transaction')
                ->where('f1.rating_value','=','1');
                $this->applyFilter($join,$post,'f1');
            })
            ->leftJoin('user_ratings as f2',function($join) use ($post){
                $join->on('f2.id_transaction','=','transactions.id_transaction')
                ->where('f2.rating_value','=','2');
                $this->applyFilter($join,$post,'f2');
            })
            ->leftJoin('user_ratings as f3',function($join) use ($post){
                $join->on('f3.id_transaction','=','transactions.id_transaction')
                ->where('f3.rating_value','=','3');
                $this->applyFilter($join,$post,'f3');
            })
            ->leftJoin('user_ratings as f4',function($join) use ($post){
                $join->on('f4.id_transaction','=','transactions.id_transaction')
                ->where('f4.rating_value','=','4');
                $this->applyFilter($join,$post,'f4');
            })
            ->leftJoin('user_ratings as f5',function($join) use ($post){
                $join->on('f5.id_transaction','=','transactions.id_transaction')
                ->where('f5.rating_value','=','5');
                $this->applyFilter($join,$post,'f5');
            })->first();
            if(!$outlet){
                return MyHelper::checkGet($outlet);
            }
            $data['outlet_data'] = $outlet;
            $post['id_outlet'] = $outlet->id_outlet;
            $counter['data'] = [];
            for ($i = 1; $i<=5 ;$i++) {
                $datax = UserRating::where('rating_value',$i)->with([
                    'transaction'=>function($query){
                        $query->select('id_transaction','transaction_receipt_number','trasaction_type','transaction_grandtotal');
                    },'user'=>function($query){
                        $query->select('id','name','phone');
                    }
                ])
                ->join('transactions','transactions.id_transaction','=','user_ratings.id_transaction')
                ->where('id_outlet',$outlet->id_outlet)
                ->take(10);
                $this->applyFilter($datax,$post);
                $counter['data'][$i] = $datax->get();
            }
            $data['rating_item'] = $counter;
            return MyHelper::checkGet($data);
        }else{
            $dasc = ($post['order']??'outlet_name')=='outlet_name'?'asc':'desc';
            $outlet = Outlet::select(\DB::raw('outlets.id_outlet,outlets.outlet_code,outlets.outlet_name,count(f1.id_user_rating) as rating1,count(f2.id_user_rating) as rating2,count(f3.id_user_rating) as rating3,count(f4.id_user_rating) as rating4,count(f5.id_user_rating) as rating5'))
            ->join('transactions','outlets.id_outlet','=','transactions.id_outlet')
            ->leftJoin('user_ratings as f1',function($join) use ($post){
                $join->on('f1.id_transaction','=','transactions.id_transaction')
                ->where('f1.rating_value','=','1');
                $this->applyFilter($join,$post,'f1');
            })
            ->leftJoin('user_ratings as f2',function($join) use ($post){
                $join->on('f2.id_transaction','=','transactions.id_transaction')
                ->where('f2.rating_value','=','2');
                $this->applyFilter($join,$post,'f2');
            })
            ->leftJoin('user_ratings as f3',function($join) use ($post){
                $join->on('f3.id_transaction','=','transactions.id_transaction')
                ->where('f3.rating_value','=','3');
                $this->applyFilter($join,$post,'f1');
            })
            ->leftJoin('user_ratings as f4',function($join) use ($post){
                $join->on('f4.id_transaction','=','transactions.id_transaction')
                ->where('f4.rating_value','=','4');
                $this->applyFilter($join,$post,'f4');
            })
            ->leftJoin('user_ratings as f5',function($join) use ($post){
                $join->on('f5.id_transaction','=','transactions.id_transaction')
                ->where('f5.rating_value','=','5');
                $this->applyFilter($join,$post,'f5');
            })
            ->orderBy($post['order']??'outlet_name',$dasc)
            ->groupBy('outlets.id_outlet');
            if($post['search']??false){
                $outlet->where(function($query) use($post){
                    $param = '%'.$post['search'].'%';
                    $query->where('outlet_name','like',$param)
                    ->orWhere('outlet_code','like',$param);
                });
            }
            return MyHelper::checkGet($outlet->paginate(15)->toArray());
        }
    }
}

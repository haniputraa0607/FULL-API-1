<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\DailyTransactions;
use App\Jobs\FraudJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\ProductCategory;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandProduct;
use App\Http\Models\ProductModifier;
use App\Http\Models\User;
use App\Http\Models\UserAddress;
use App\Http\Models\Outlet;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionProductModifier;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPickupGoSend;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\UserOutlet;
use App\Http\Models\TransactionSetting;
use App\Http\Models\FraudSetting;
use App\Http\Models\Configs;
use App\Http\Models\Holiday;
use App\Http\Models\OutletToken;
use App\Http\Models\UserLocationDetail;
use App\Http\Models\TransactionVoucher;
use App\Http\Models\Deal;
use App\Http\Models\DealsUser;
use Modules\ProductVariant\Entities\ProductVariant;
use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\PromoCampaign\Entities\PromoCampaignReferral;
use Modules\PromoCampaign\Entities\PromoCampaignReferralTransaction;
use Modules\PromoCampaign\Entities\UserReferralCode;
use Modules\PromoCampaign\Entities\PromoCampaignReport;

use Modules\Balance\Http\Controllers\NewTopupController;
use Modules\PromoCampaign\Lib\PromoCampaignTools;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request as RequestGuzzle;
use Guzzle\Http\Message\Response as ResponseGuzzle;
use Guzzle\Http\Exception\ServerErrorResponseException;

use DB;
use App\Lib\MyHelper;
use App\Lib\Midtrans;
use App\Lib\GoSend;
use App\Lib\Ovo;

use Modules\Transaction\Http\Requests\Transaction\NewTransaction;
use Modules\Transaction\Http\Requests\Transaction\ConfirmPayment;
use Modules\Transaction\Http\Requests\CheckTransaction;
use DateTime;

use Modules\UserRating\Entities\UserRatingLog;

class ApiOnlineTransaction extends Controller
{
    public $saveImage = "img/payment/manual/";

    function __construct() {
        ini_set('max_execution_time', 0);
        date_default_timezone_set('Asia/Jakarta');

        $this->balance       = "Modules\Balance\Http\Controllers\BalanceController";
        $this->membership    = "Modules\Membership\Http\Controllers\ApiMembership";
        $this->autocrm       = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->transaction   = "Modules\Transaction\Http\Controllers\ApiTransaction";
        $this->notif         = "Modules\Transaction\Http\Controllers\ApiNotification";
        $this->setting_fraud = "Modules\SettingFraud\Http\Controllers\ApiFraud";
        $this->setting_trx   = "Modules\Transaction\Http\Controllers\ApiSettingTransactionV2";
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->promo       = "Modules\PromoCampaign\Http\Controllers\ApiPromo";
    }

    public function newTransaction(NewTransaction $request) {
        $post = $request->json()->all();
        if (!isset($post['id_user'])) {
            $id = $request->user()->id;
        } else {
            $id = $post['id_user'];
        }

        $user = User::with('memberships')->where('id', $id)->first();
        if (empty($user)) {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['User Not Found']
            ]);
        }

        //check verify email
        if(isset($user['email_verified']) && $user['email_verified'] != '1'){
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'message_verfiy_email'=> 'Sorry your email has not yet been verified. Please verify your email.',
                'messages'  => ['Sorry your email has not yet been verified. Please verify your email.']
            ]);
        }

        //suspend
        if(isset($user['is_suspended']) && $user['is_suspended'] == '1'){
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Sorry your account has been suspended, please contact '.config('configs.EMAIL_ADDRESS_ADMIN')]
            ]);
        }

        $config_fraud_use_queue = Configs::where('config_name', 'fraud use queue')->first()->is_active;

        $use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();
        if($use_product_variant && !isset($post['from_fake'])){
            foreach ($post['item'] as &$prod) {
                $prd = Product::where(function($query) use ($prod){
                    foreach($prod['variants'] as $variant){
                        $query->whereHas('product_variants',function($query) use ($variant){
                            $query->where('product_variants.id_product_variant',$variant);
                        });
                    }
                })->where('id_product_group',$prod['id_product_group'])->first();
                if(!$prd){
                    return [
                        'status' => 'fail',
                        'messages' => ['Product not found']
                    ];
                }
                $prod['id_product'] = $prd['id_product'];
            }
        }
        // return $post;
        $totalPrice = 0;
        $totalWeight = 0;
        $totalDiscount = 0;
        $grandTotal = app($this->setting_trx)->grandTotal();
        $order_id = null;
        $id_pickup_go_send = null;
        $promo_code_ref = null;

        if (isset($post['headers'])) {
            unset($post['headers']);
        }

        $dataInsertProduct = [];
        $productMidtrans = [];
        $dataDetailProduct = [];
        $userTrxProduct = [];

        $outlet = Outlet::where('id_outlet', $post['id_outlet'])->with('today')->first();
        if (empty($outlet)) {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Outlet Not Found']
                ]);
        }

        $fraudTrxDay = FraudSetting::where('parameter', 'LIKE', '%transactions in 1 day%')->where('fraud_settings_status','Active')->first();
        $fraudTrxWeek = FraudSetting::where('parameter', 'LIKE', '%transactions in 1 week%')->where('fraud_settings_status','Active')->first();

        $issetDate = false;
        if (isset($post['transaction_date'])) {
            $issetDate = true;
            $post['transaction_date'] = date('Y-m-d H:i:s', strtotime($post['transaction_date']));
        } else {
            $post['transaction_date'] = date('Y-m-d H:i:s');
        }

        //cek outlet active
        if(isset($outlet['outlet_status']) && $outlet['outlet_status'] == 'Inactive'){
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Outlet is closed']
            ]);
        }

        if($post['payment_type'] == 'Ovo' && !Ovo::checkOutletOvo($post['id_outlet'])){
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Ovo payment not available at this store']
            ]);
        }

        //cek outlet holiday
        if($issetDate == false){
            $holiday = Holiday::join('outlet_holidays', 'holidays.id_holiday', 'outlet_holidays.id_holiday')->join('date_holidays', 'holidays.id_holiday', 'date_holidays.id_holiday')
                    ->where('id_outlet', $outlet['id_outlet'])->whereDay('date_holidays.date', date('d'))->whereMonth('date_holidays.date', date('m'))->get();
            if(count($holiday) > 0){
                foreach($holiday as $i => $holi){
                    if($holi['yearly'] == '0'){
                        if($holi['date'] == date('Y-m-d')){
                            DB::rollBack();
                            return response()->json([
                                'status'    => 'fail',
                                'messages'  => ['Outlet is closed']
                            ]);
                        }
                    }else{
                        DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'  => ['Outlet is closed']
                        ]);
                    }
                }
            }

            if($outlet['today']['is_closed'] == '1'){
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Outlet is closed']
                ]);
            }

             if($outlet['today']['close'] && $outlet['today']['close'] != "00:00" && $outlet['today']['open'] && $outlet['today']['open'] != '00:00'){

                $settingTime = Setting::where('key', 'processing_time')->first();
                if($settingTime && $settingTime->value){
                    if($outlet['today']['close'] && date('H:i') > date('H:i', strtotime('-'.$settingTime->value.' minutes' ,strtotime($outlet['today']['close'])))){
                        DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'  => ['Outlet is closed']
                        ]);
                    }
                }

                //cek outlet open - close hour
                if(($outlet['today']['open'] && date('H:i') < date('H:i', strtotime($outlet['today']['open']))) || ($outlet['today']['close'] && date('H:i') > date('H:i', strtotime($outlet['today']['close'])))){
                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Outlet is closed']
                    ]);
                }
            }
        }

        if (isset($post['transaction_payment_status'])) {
            $post['transaction_payment_status'] = $post['transaction_payment_status'];
        } else {
            $post['transaction_payment_status'] = 'Pending';
        }

        if (count($user['memberships']) > 0) {
            $post['membership_level']    = $user['memberships'][0]['membership_name'];
            $post['membership_promo_id'] = $user['memberships'][0]['benefit_promo_id'];
        } else {
            $post['membership_level']    = null;
            $post['membership_promo_id'] = null;
        }

        if ($post['type'] == 'Delivery') {
            $userAddress = UserAddress::where(['id_user' => $id, 'id_user_address' => $post['id_user_address']])->first();

            if (empty($userAddress)) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Address Not Found']
                ]);
            }
        }

        $totalDisProduct = 0;

        // $productDis = $this->countDis($post);
        $productDis = app($this->setting_trx)->discountProduct($post);
        if ($productDis) {
            $totalDisProduct = $productDis;
        }

        // remove bonus item
        $pct = new PromoCampaignTools();
        $post['item'] = $pct->removeBonusItem($post['item']);

        // check promo code and referral
        $promo_error=[];
        $use_referral = false;
        $discount_promo = [];
        $promo_discount = 0;
        $promo_source = null;
        if($request->json('promo_code') && !$request->json('id_deals_user')){
            $code=PromoCampaignPromoCode::where('promo_code',$request->promo_code)
                ->join('promo_campaigns', 'promo_campaigns.id_promo_campaign', '=', 'promo_campaign_promo_codes.id_promo_campaign')
                ->where( function($q){
                    $q->whereColumn('usage','<','limitation_usage')
                        ->orWhere('code_type','Single')
                        ->orWhere('limitation_usage',0);
                } )
                ->first();
            if ($code)
            {
                $post['id_promo_campaign_promo_code'] = $code->id_promo_campaign_promo_code;
                if($code->promo_type == "Referral"){
                    $promo_code_ref = $request->json('promo_code');
                    $use_referral = true;
                }
                $pct=new PromoCampaignTools();
                $validate_user=$pct->validateUser($code->id_promo_campaign, $request->user()->id, $request->user()->phone, $request->device_type, $request->device_id, $errore,$code->id_promo_campaign_promo_code);

                $discount_promo=$pct->validatePromo($code->id_promo_campaign, $request->id_outlet, $post['item'], $errors);

                if ( !empty($errore) || !empty($errors)) {
                    DB::rollBack();
                    return [
                        'status'=>'fail',
                        'messages'=>['Promo code not valid']
                    ];
                }
                $promo_source = 'promo_code';
                $promo_discount=$discount_promo['discount'];
            }
            else
            {
                return [
                    'status'=>'fail',
                    'messages'=>['Promo code not valid']
                ];
            }
        }
        elseif($request->json('id_deals_user') && !$request->json('promo_code'))
        {
        	$deals = app($this->promo_campaign)->checkVoucher($request->id_deals_user, 1);

			if($deals)
			{
				$pct=new PromoCampaignTools();
				$discount_promo=$pct->validatePromo($deals->dealVoucher->id_deals, $request->id_outlet, $post['item'], $errors, 'deals');

				if ( !empty($errors) ) {
					DB::rollBack();
                    return [
                        'status'=>'fail',
                        'messages'=>['Voucher is not valid']
                    ];
	            }

                $promo_source = 'voucher_online';
	            $promo_discount=$discount_promo['discount'];
	        }
	        else
	        {
	        	return [
                    'status'=>'fail',
                    'messages'=>['Voucher is not valid']
                ];
	        }
        }
        elseif($request->json('id_deals_user') && $request->json('promo_code'))
        {
        	return [
                'status'=>'fail',
                'messages'=>['Voucher is not valid']
            ];
        }
        // end check promo

        $error_msg=[];

        foreach ($grandTotal as $keyTotal => $valueTotal) {
            if ($valueTotal == 'subtotal') {
                $post['sub'] = app($this->setting_trx)->countTransaction($valueTotal, $post, $discount_promo);
                // $post['sub'] = $this->countTransaction($valueTotal, $post);
                if (gettype($post['sub']) != 'array') {
                    $mes = ['Data Not Valid'];

                    if (isset($post['sub']->original['messages'])) {
                        $mes = $post['sub']->original['messages'];

                        if ($post['sub']->original['messages'] == ['Price Product Not Found']) {
                            if (isset($post['sub']->original['product'])) {
                                $mes = ['Price Product Not Found with product '.$post['sub']->original['product'].' at outlet '.$outlet['outlet_name']];
                            }
                        }

                        if ($post['sub']->original['messages'] == ['Price Product Not Valid']) {
                            if (isset($post['sub']->original['product'])) {
                                $mes = ['Price Product Not Valid with product '.$post['sub']->original['product'].' at outlet '.$outlet['outlet_name']];
                            }
                        }
                    }

                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => $mes
                    ]);
                }

                $post['subtotal'] = array_sum($post['sub']);
                $post['subtotal'] = $post['subtotal'] - $totalDisProduct;
            } elseif ($valueTotal == 'discount') {
                // $post['dis'] = $this->countTransaction($valueTotal, $post);
                $post['dis'] = app($this->setting_trx)->countTransaction($valueTotal, $post, $discount_promo);
                $mes = ['Data Not Valid'];

                if (isset($post['dis']->original['messages'])) {
                    $mes = $post['dis']->original['messages'];

                    if ($post['dis']->original['messages'] == ['Price Product Not Found']) {
                        if (isset($post['dis']->original['product'])) {
                            $mes = ['Price Product Not Found with product '.$post['dis']->original['product'].' at outlet '.$outlet['outlet_name']];
                        }
                    }

                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => $mes
                    ]);
                }

                $post['discount'] = $post['dis'] + $totalDisProduct;
            }elseif($valueTotal == 'tax'){
                $post['tax'] = app($this->setting_trx)->countTransaction($valueTotal, $post);
                $mes = ['Data Not Valid'];

                    if (isset($post['tax']->original['messages'])) {
                        $mes = $post['tax']->original['messages'];

                        if ($post['tax']->original['messages'] == ['Price Product Not Found']) {
                            if (isset($post['tax']->original['product'])) {
                                $mes = ['Price Product Not Found with product '.$post['tax']->original['product'].' at outlet '.$outlet['outlet_name']];
                            }
                        }

                        if ($post['sub']->original['messages'] == ['Price Product Not Valid']) {
                            if (isset($post['tax']->original['product'])) {
                                $mes = ['Price Product Not Valid with product '.$post['tax']->original['product'].' at outlet '.$outlet['outlet_name']];
                            }
                        }

                        DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'  => $mes
                        ]);
                    }
            }
            else {
                $post[$valueTotal] = app($this->setting_trx)->countTransaction($valueTotal, $post);
            }
        }

        $post['point'] = app($this->setting_trx)->countTransaction('point', $post);
        $post['cashback'] = app($this->setting_trx)->countTransaction('cashback', $post);

        //count some trx user
        $countUserTrx = Transaction::where('id_user', $id)->where('transaction_payment_status', 'Completed')->count();

        $countSettingCashback = TransactionSetting::get();

        // return $countSettingCashback;
        if ($countUserTrx < count($countSettingCashback)) {
            // return $countUserTrx;
            $post['cashback'] = $post['cashback'] * $countSettingCashback[$countUserTrx]['cashback_percent'] / 100;

            if ($post['cashback'] > $countSettingCashback[$countUserTrx]['cashback_maximum']) {
                $post['cashback'] = $countSettingCashback[$countUserTrx]['cashback_maximum'];
            }
        } else {

            $maxCash = Setting::where('key', 'cashback_maximum')->first();

            if (count($user['memberships']) > 0) {
                $post['point'] = $post['point'] * ($user['memberships'][0]['benefit_point_multiplier']) / 100;
                $post['cashback'] = $post['cashback'] * ($user['memberships'][0]['benefit_cashback_multiplier']) / 100;

                if($user['memberships'][0]['cashback_maximum']){
                    $maxCash['value'] = $user['memberships'][0]['cashback_maximum'];
                }
            }

            $statusCashMax = 'no';

            if (!empty($maxCash) && !empty($maxCash['value'])) {
                $statusCashMax = 'yes';
                $totalCashMax = $maxCash['value'];
            }

            if ($statusCashMax == 'yes') {
                if ($totalCashMax < $post['cashback']) {
                    $post['cashback'] = $totalCashMax;
                }
            } else {
                $post['cashback'] = $post['cashback'];
            }
        }


        if (!isset($post['payment_type'])) {
            $post['payment_type'] = null;
        }

        if (!isset($post['shipping'])) {
            $post['shipping'] = 0;
        }

        if (!isset($post['subtotal'])) {
            $post['subtotal'] = 0;
        }

        if (!isset($post['discount'])) {
            $post['discount'] = 0;
        }

        if (!isset($post['service'])) {
            $post['service'] = 0;
        }

        if (!isset($post['tax'])) {
            $post['tax'] = 0;
        }

        $post['discount'] = -$post['discount'];

        if (isset($post['payment_type']) && $post['payment_type'] == 'Balance') {
            $post['cashback'] = 0;
            $post['point']    = 0;
        }

        if ($request->json('promo_code') || $request->json('id_deals_user')) {
        	$check = $this->checkPromoGetPoint($promo_source);
        	if ( $check == 0 ) {
        		$post['cashback'] = 0;
            	$post['point']    = 0;
        	}
        }

        // apply cashback
        if ($use_referral){
            $referral_rule = PromoCampaignReferral::where('id_promo_campaign',$code->id_promo_campaign)->first();
            if(!$referral_rule){
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Referrer Cashback Failed']
                ]);
            }
            $referred_cashback = 0;
            if($referral_rule->referred_promo_type == 'Cashback'){
                if($referral_rule->referred_promo_unit == 'Percent'){
                    $referred_discount_percent = $referral_rule->referred_promo_value<=100?$referral_rule->referred_promo_value:100;
                    $referred_cashback = $post['subtotal']*$referred_discount_percent/100;
                }else{
                    if($post['subtotal'] >= $referral_rule->referred_min_value){
                        $referred_cashback = $referral_rule->referred_promo_value<=$post['subtotal']?$referral_rule->referred_promo_value:$post['subtotal'];
                    }
                }
            }
            $post['cashback'] = $referred_cashback;
        }

        $detailPayment = [
            'subtotal' => $post['subtotal'],
            'shipping' => $post['shipping'],
            'tax'      => $post['tax'],
            'service'  => $post['service'],
            'discount' => $post['discount'],
        ];

        // return $detailPayment;
        $post['grandTotal'] = (double)$post['subtotal'] + (double)$post['discount'] + (double)$post['service'] + (double)$post['tax'] + (double)$post['shipping'];
        // return $post;
        if ($post['type'] == 'Delivery') {
            $dataUser = [
                'first_name'      => $user['name'],
                'email'           => $user['email'],
                'phone'           => $user['phone'],
                'billing_address' => [
                    'first_name'  => $userAddress['name'],
                    'phone'       => $userAddress['phone'],
                    'address'     => $userAddress['address'],
                    'postal_code' => $userAddress['postal_code']
                ],
            ];

            $dataShipping = [
                'first_name'  => $userAddress['name'],
                'phone'       => $userAddress['phone'],
                'address'     => $userAddress['address'],
                'postal_code' => $userAddress['postal_code']
            ];
        } elseif($post['type'] == 'Pickup Order') {
            $dataUser = [
                'first_name'      => $user['name'],
                'email'           => $user['email'],
                'phone'           => $user['phone'],
                'billing_address' => [
                    'first_name'  => $user['name'],
                    'phone'       => $user['phone']
                ],
            ];
        } elseif($post['type'] == 'GO-SEND'){
            //check key GO-SEND
            $dataAddress = $post['destination'];
            $dataAddress['latitude'] = number_format($dataAddress['latitude'],8);
            $dataAddress['longitude'] = number_format($dataAddress['longitude'],8);
            if($dataAddress['id_user_address']??false){
                $dataAddressKeys = ['id_user_address'=>$dataAddress['id_user_address']];
            }else{
                $dataAddressKeys = [
                    'latitude' => number_format($dataAddress['latitude'],8),
                    'longitude' => number_format($dataAddress['longitude'],8)
                ];
            }
            $dataAddressKeys['id_user'] = $user['id'];
            $addressx = UserAddress::where($dataAddressKeys)->first();
            if(!$addressx){
                $addressx = UserAddress::create($dataAddressKeys+$dataAddress);
            }elseif(!$addressx->favorite){
                $addressx->update($dataAddress);
            }
            $checkKey = GoSend::checkKey();
            if(is_array($checkKey) && $checkKey['status'] == 'fail'){
                DB::rollback();
                return response()->json($checkKey);
            }

            $dataUser = [
                'first_name'      => $user['name'],
                'email'           => $user['email'],
                'phone'           => $user['phone'],
                'billing_address' => [
                    'first_name'  => $user['name'],
                    'phone'       => $user['phone']
                ],
            ];
            $dataShipping = [
                'name'        => $user['name'],
                'phone'       => $user['phone'],
                'address'     => $post['destination']['address']
            ];
        }

        if (!isset($post['latitude'])) {
            $post['latitude'] = null;
        }

        if (!isset($post['longitude'])) {
            $post['longitude'] = null;
        }

        if (!isset($post['notes'])) {
            $post['notes'] = null;
        }

        $type = $post['type'];
        $isFree = '0';
        $shippingGoSend = 0;

        if ($post['type'] == 'GO-SEND') {
            if(!($outlet['outlet_latitude']&&$outlet['outlet_longitude']&&$outlet['outlet_phone']&&$outlet['outlet_address'])){
                app($this->outlet)->sendNotifIncompleteOutlet($outlet['id_outlet']);
                $outlet->notify_admin = 1;
                $outlet->save();
                return [
                    'status' => 'fail',
                    'messages' => ['Tidak dapat melakukan pengiriman dari outlet ini']
                ];
            }
            $coor_origin = [
                'latitude' => number_format($outlet['outlet_latitude'],8),
                'longitude' => number_format($outlet['outlet_longitude'],8)
            ];
            $coor_destination = [
                'latitude' => number_format($post['destination']['latitude'],8),
                'longitude' => number_format($post['destination']['longitude'],8)
            ];
            $type = 'Pickup Order';
            $shippingGoSendx = GoSend::getPrice($coor_origin,$coor_destination);
            $shippingGoSend = $shippingGoSendx[GoSend::getShipmentMethod()]['price']['total_price']??null;
            if($shippingGoSend === null){
                return [
                    'status' => 'fail',
                    'messagse' => array_column($shippingGoSendx[GoSend::getShipmentMethod()]['errors']??[],'message')?:['Gagal menghitung ongkos kirim']
                ];
            }
            //cek free delivery
            // if($post['is_free'] == 'yes'){
            //     $isFree = '1';
            // }
            $isFree = 0;
        }

        if ($post['grandTotal'] < 0 || $post['subtotal'] < 0) {
            return [
                'status' => 'fail',
                'messages' => ['Invalid transaction']
            ];
        }
        DB::beginTransaction();
        $transaction = [
            'id_outlet'                   => $post['id_outlet'],
            'id_user'                     => $id,
            'id_promo_campaign_promo_code'           => $post['id_promo_campaign_promo_code']??null,
            'transaction_date'            => $post['transaction_date'],
            'transaction_receipt_number'  => 'TRX-'.date('ymd').MyHelper::createrandom(6,'Besar'),
            'trasaction_type'             => $type,
            'transaction_notes'           => $post['notes'],
            'transaction_subtotal'        => $post['subtotal'],
            'transaction_shipment'        => $post['shipping'],
            'transaction_shipment_go_send'=> $shippingGoSend,
            'transaction_is_free'         => $isFree,
            'transaction_service'         => $post['service'],
            'transaction_discount'        => $post['discount'],
            'transaction_tax'             => $post['tax'],
            'transaction_grandtotal'      => $post['grandTotal'] + $shippingGoSend,
            'transaction_point_earned'    => $post['point'],
            'transaction_cashback_earned' => MyHelper::requestNumber($post['cashback'],'point'),
            'trasaction_payment_type'     => $post['payment_type'],
            'transaction_payment_status'  => $post['transaction_payment_status'],
            'membership_level'            => $post['membership_level'],
            'membership_promo_id'         => $post['membership_promo_id'],
            'latitude'                    => $post['latitude'],
            'longitude'                   => $post['longitude'],
            'void_date'                   => null,
        ];

        if($transaction['transaction_grandtotal'] == 0){
            $transaction['transaction_payment_status'] = 'Completed';
            $transaction['completed_at'] = date('Y-m-d H:i:s');
        }

        $newTopupController = new NewTopupController();
        $checkHashBefore = $newTopupController->checkHash('log_balances', $id);
        if (!$checkHashBefore) {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Your previous transaction data is invalid']
            ]);
        }

        $useragent = $_SERVER['HTTP_USER_AGENT'];
        if(stristr($useragent,'iOS')) $useragent = 'IOS';
        elseif(stristr($useragent,'okhttp')) $useragent = 'Android';
        else $useragent = null;

        if($useragent){
            $transaction['transaction_device_type'] = $useragent;
        }

        $insertTransaction = Transaction::create($transaction);

        if (!$insertTransaction) {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Insert Transaction Failed']
            ]);
        }
        // add report referral
        if($use_referral){
            $addPromoCounter = PromoCampaignReferralTransaction::create([
                'id_promo_campaign_promo_code' =>$code->id_promo_campaign_promo_code,
                'id_user' => $insertTransaction['id_user'],
                'id_referrer' => UserReferralCode::select('id_user')->where('id_promo_campaign_promo_code',$code->id_promo_campaign_promo_code)->pluck('id_user')->first(),
                'id_transaction' => $insertTransaction['id_transaction'],
                'referred_bonus_type' => $promo_discount?'Product Discount':'Cashback',
                'referred_bonus' => $promo_discount?:$insertTransaction['transaction_cashback_earned']
            ]);
            if(!$addPromoCounter){
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Transaction Failed']
                ]);
            }

            $promo_code_ref = $request->promo_code;
        }
        // add transaction voucher
        if($request->json('id_deals_user')){
        	$update_voucher = DealsUser::where('id_deals_user','=',$request->id_deals_user)->update(['used_at' => date('Y-m-d H:i:s'), 'id_outlet' => $request->json('id_outlet'), 'redeemed_at' => date('Y-m-d H:i:s')]);
        	// $update_deals = Deal::where('id_deals','=',$deals->dealVoucher['deals']['id_deals'])->update(['deals_total_used' => $deals->dealVoucher['deals']['deals_total_used']+1]);

            $addTransactionVoucher = TransactionVoucher::create([
                'id_deals_voucher' => $deals['id_deals_voucher'],
                'id_user' => $insertTransaction['id_user'],
                'id_transaction' => $insertTransaction['id_transaction']
            ]);
            if(!$addTransactionVoucher){
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Transaction Failed']
                ]);
            }
        }

        // add promo campaign report
        if($request->json('promo_code'))
        {
        	$promo_campaign_report = app($this->promo_campaign)->addReport(
				$code->id_promo_campaign,
				$code->id_promo_campaign_promo_code,
				$insertTransaction['id_transaction'],
				$insertTransaction['id_outlet'],
				$request->device_id?:'',
				$request->device_type?:''
			);

        	if (!$promo_campaign_report) {
        		DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Transaction Failed']
                ]);
        	}
        }
        //update receipt
        // $receipt = 'TRX-'.MyHelper::createrandom(6,'Angka').time().MyHelper::createrandom(3,'Angka').$insertTransaction['id_outlet'].MyHelper::createrandom(3,'Angka');
        // $updateReceiptNumber = Transaction::where('id_transaction', $insertTransaction['id_transaction'])->update([
        //     'transaction_receipt_number' => $receipt
        // ]);

        // if (!$updateReceiptNumber) {
        //     DB::rollBack();
        //     return response()->json([
        //         'status'    => 'fail',
        //         'messages'  => ['Insert Transaction Failed']
        //     ]);
        // }

        MyHelper::updateFlagTransactionOnline($insertTransaction, 'pending', $user);

        // $insertTransaction['transaction_receipt_number'] = $receipt;

        foreach (($discount_promo['item']??$post['item']) as $keyProduct => $valueProduct) {

            $this_discount=$valueProduct['discount']??0;

            $checkProduct = Product::where('id_product', $valueProduct['id_product'])->first();
            if (empty($checkProduct)) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Product Not Found']
                ]);
            }

            $checkPriceProduct = ProductPrice::where(['id_product' => $checkProduct['id_product'], 'id_outlet' => $post['id_outlet']])->first();
            if (empty($checkPriceProduct)) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Product Price Not Valid']
                ]);
            }

            if ($checkPriceProduct['product_stock_status'] == 'Sold Out') {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => [$checkProduct['product_name'].' is out of stock']
                ]);
            }

            if(!isset($valueProduct['note'])){
                $valueProduct['note'] = null;
            }

            $dataProduct = [
                'id_transaction'               => $insertTransaction['id_transaction'],
                'id_product'                   => $checkProduct['id_product'],
                'id_brand'                     => $valueProduct['id_brand'],
                'id_outlet'                    => $insertTransaction['id_outlet'],
                'id_user'                      => $insertTransaction['id_user'],
                'transaction_product_qty'      => $valueProduct['qty'],
                'transaction_product_price'    => $checkPriceProduct['product_price'],
                'transaction_product_price_base'    => $checkPriceProduct['product_price_base'],
                'transaction_product_price_tax'    => $checkPriceProduct['product_price_tax'],
                'transaction_product_discount'   => $this_discount,
                // remove discount from subtotal
                // 'transaction_product_subtotal' => ($valueProduct['qty'] * $checkPriceProduct['product_price'])-$this_discount,
                'transaction_product_subtotal' => ($valueProduct['qty'] * $checkPriceProduct['product_price']),
                'transaction_product_note'     => $valueProduct['note'],
                'created_at'                   => date('Y-m-d', strtotime($insertTransaction['transaction_date'])).' '.date('H:i:s'),
                'updated_at'                   => date('Y-m-d H:i:s')
            ];

            $trx_product = TransactionProduct::create($dataProduct);
            if (!$trx_product) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Product Transaction Failed']
                ]);
            }
            if(strtotime($insertTransaction['transaction_date'])){
                $trx_product->created_at = strtotime($insertTransaction['transaction_date']);
            }
            // array_push($dataInsertProduct, $dataProduct);

            $insert_modifier = [];
            $mod_subtotal = 0;
            $more_mid_text = '';
            foreach ($valueProduct['modifiers'] as $modifier) {
                $id_product_modifier = is_numeric($modifier)?$modifier:$modifier['id_product_modifier'];
                $qty_product_modifier = is_numeric($modifier)?1:$modifier['qty'];
                $mod = ProductModifier::select('product_modifiers.id_product_modifier','code','type','text','product_modifier_stock_status','product_modifier_price')
                    // produk modifier yang tersedia di outlet
                    ->join('product_modifier_prices','product_modifiers.id_product_modifier','=','product_modifier_prices.id_product_modifier')
                    ->where('product_modifier_prices.id_outlet',$post['id_outlet'])
                    // produk aktif
                    ->where('product_modifier_status','Active')
                    // product visible
                    ->where(function($query){
                        $query->where('product_modifier_prices.product_modifier_visibility','=','Visible')
                        ->orWhere(function($q){
                            $q->whereNull('product_modifier_prices.product_modifier_visibility')
                            ->where('product_modifiers.product_modifier_visibility', 'Visible');
                        });
                    })
                    ->groupBy('product_modifiers.id_product_modifier')
                    // product modifier dengan id
                    ->find($id_product_modifier);
                if(!$mod){
                    return [
                        'status' => 'fail',
                        'messages' => ['Modifier not found']
                    ];
                }
                if($mod['product_modifier_stock_status']!='Available'){
                    return [
                        'status' => 'fail',
                        'messages' => ['Modifier not available']
                    ];
                }
                $mod = $mod->toArray();
                $insert_modifier[] = [
                    'id_transaction_product'=>$trx_product['id_transaction_product'],
                    'id_transaction'=>$insertTransaction['id_transaction'],
                    'id_product'=>$checkProduct['id_product'],
                    'id_product_modifier'=>$id_product_modifier,
                    'id_outlet'=>$insertTransaction['id_outlet'],
                    'id_user'=>$insertTransaction['id_user'],
                    'type'=>$mod['type']??'',
                    'code'=>$mod['code']??'',
                    'text'=>$mod['text']??'',
                    'qty'=>$qty_product_modifier,
                    'transaction_product_modifier_price'=>$mod['product_modifier_price']*$qty_product_modifier,
                    'datetime'=>$insertTransaction['transaction_date']??date(),
                    'trx_type'=>$type,
                    // 'sales_type'=>'',
                    'created_at'                   => date('Y-m-d H:i:s'),
                    'updated_at'                   => date('Y-m-d H:i:s')
                ];
                $mod_subtotal += $mod['product_modifier_price']*$qty_product_modifier;
                if($qty_product_modifier>1){
                    $more_mid_text .= ','.$qty_product_modifier.'x '.$mod['text'];
                }else{
                    $more_mid_text .= ','.$mod['text'];
                }
            }
            $trx_modifier = TransactionProductModifier::insert($insert_modifier);
            if (!$trx_modifier) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Product Modifier Transaction Failed']
                ]);
            }
            $trx_product->transaction_modifier_subtotal = $mod_subtotal;
            $trx_product->transaction_product_subtotal += $trx_product->transaction_modifier_subtotal * $valueProduct['qty'];
            $trx_product->save();
            $dataProductMidtrans = [
                'id'       => $checkProduct['id_product'],
                'price'    => $checkPriceProduct['product_price']+$mod_subtotal,
                // 'name'     => $checkProduct['product_name'].($more_mid_text?'('.trim($more_mid_text,',').')':''), // name & modifier too long
                'name'     => $checkProduct['product_name'],
                'quantity' => $valueProduct['qty'],
            ];
            array_push($productMidtrans, $dataProductMidtrans);
            $totalWeight += $checkProduct['product_weight'] * $valueProduct['qty'];

            $dataUserTrxProduct = [
                'id_user'       => $insertTransaction['id_user'],
                'id_product'    => $checkProduct['id_product'],
                'product_qty'   => $valueProduct['qty'],
                'last_trx_date' => $insertTransaction['transaction_date']
            ];
            array_push($userTrxProduct, $dataUserTrxProduct);
        }

        array_push($dataDetailProduct, $productMidtrans);

        $dataShip = [
            'id'       => null,
            'price'    => $post['shipping'],
            'name'     => 'Shipping',
            'quantity' => 1,
        ];
        array_push($dataDetailProduct, $dataShip);

        $dataService = [
            'id'       => null,
            'price'    => $post['service'],
            'name'     => 'Service',
            'quantity' => 1,
        ];
        array_push($dataDetailProduct, $dataService);

        $dataTax = [
            'id'       => null,
            'price'    => $post['tax'],
            'name'     => 'Tax',
            'quantity' => 1,
        ];
        array_push($dataDetailProduct, $dataTax);

        $dataDis = [
            'id'       => null,
            'price'    => -$post['discount'],
            'name'     => 'Discount',
            'quantity' => 1,
        ];
        array_push($dataDetailProduct, $dataDis);

        // $insrtProduct = TransactionProduct::insert($dataInsertProduct);
        // if (!$insrtProduct) {
        //     DB::rollBack();
        //     return response()->json([
        //         'status'    => 'fail',
        //         'messages'  => ['Insert Product Transaction Failed']
        //     ]);
        // }
        $insertUserTrxProduct = app($this->transaction)->insertUserTrxProduct($userTrxProduct);
        if ($insertUserTrxProduct == 'fail') {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Insert Product Transaction Failed']
            ]);
        }

        if (isset($post['receive_at']) && $post['receive_at']) {
            $post['receive_at'] = date('Y-m-d H:i:s', strtotime($post['receive_at']));
        } else {
            $post['receive_at'] = null;
        }

        if (isset($post['id_admin_outlet_receive'])) {
            $post['id_admin_outlet_receive'] = $post['id_admin_outlet_receive'];
        } else {
            $post['id_admin_outlet_receive'] = null;
        }

        $configAdminOutlet = Configs::where('config_name', 'admin outlet')->first();

        if($configAdminOutlet && $configAdminOutlet['is_active'] == '1'){

            if ($post['type'] == 'Delivery') {
                $configAdminOutlet = Configs::where('config_name', 'admin outlet delivery order')->first();
            }else{
                $configAdminOutlet = Configs::where('config_name', 'admin outlet pickup order')->first();
            }

            if($configAdminOutlet && $configAdminOutlet['is_active'] == '1'){
                $adminOutlet = UserOutlet::where('id_outlet', $insertTransaction['id_outlet'])->orderBy('id_user_outlet');
            }
        }


        //sum balance
        $sumBalance = LogBalance::where('id_user', $id)->sum('balance');
        if ($post['type'] == 'Delivery') {
            $link = '';
            if($configAdminOutlet && $configAdminOutlet['is_active'] == '1'){
                $totalAdmin = $adminOutlet->where('delivery', 1)->first();
                if (empty($totalAdmin)) {
                    DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Admin outlet is empty']
                    ]);
                }

                $link = env('APP_URL').'/transaction/admin/'.$insertTransaction['transaction_receipt_number'].'/'.$totalAdmin['phone'];
            }

            $order_id = MyHelper::createrandom(4, 'Besar Angka');

            //cek unique order id uniq today and outlet
            $cekOrderId = TransactionShipment::join('transactions', 'transactions.id_transaction', 'transaction_shipments.id_transaction')
                                            ->where('id_outlet', $insertTransaction['id_outlet'])
                                            ->where('order_id', $order_id)
                                            ->whereDate('transaction_date', date('Y-m-d'))
                                            ->first();
            while ($cekOrderId) {
                $order_id = MyHelper::createrandom(4, 'Besar Angka');

                $cekOrderId = TransactionShipment::join('transactions', 'transactions.id_transaction', 'transaction_shipments.id_transaction')
                                                ->where('id_outlet', $insertTransaction['id_outlet'])
                                                ->where('order_id', $order_id)
                                                ->whereDate('transaction_date', date('Y-m-d'))
                                                ->first();
            }

            if (isset($post['send_at'])) {
                $post['send_at'] = date('Y-m-d H:i:s', strtotime($post['send_at']));
            } else {
                $post['send_at'] = null;
            }

            if (isset($post['id_admin_outlet_send'])) {
                $post['id_admin_outlet_send'] = $post['id_admin_outlet_send'];
            } else {
                $post['id_admin_outlet_send'] = null;
            }

            $dataShipment = [
                'id_transaction'           => $insertTransaction['id_transaction'],
                'order_id'                 => $order_id,
                'depart_name'              => $outlet['outlet_name'],
                'depart_phone'             => $outlet['outlet_phone'],
                'depart_address'           => $outlet['outlet_address'],
                'depart_id_city'           => $outlet['id_city'],
                'destination_name'         => $userAddress['name'],
                'destination_phone'        => $userAddress['phone'],
                'destination_address'      => $userAddress['address'],
                'destination_id_city'      => $userAddress['id_city'],
                'destination_description'  => $userAddress['description'],
                'shipment_total_weight'    => $totalWeight,
                'shipment_courier'         => $post['courier'],
                'shipment_courier_service' => $post['cour_service'],
                'shipment_courier_etd'     => $post['cour_etd'],
                'receive_at'               => $post['receive_at'],
                'id_admin_outlet_receive'  => $post['id_admin_outlet_receive'],
                'send_at'                  => $post['send_at'],
                'id_admin_outlet_send'     => $post['id_admin_outlet_send'],
                'short_link'               => $link
            ];

            $insertShipment = TransactionShipment::create($dataShipment);
            if (!$insertShipment) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Shipment Transaction Failed']
                ]);
            }
        } elseif ($post['type'] == 'Pickup Order' || $post['type'] == 'GO-SEND') {
            $link = '';
            if($configAdminOutlet && $configAdminOutlet['is_active'] == '1'){
                $totalAdmin = $adminOutlet->where('pickup_order', 1)->first();
                if (empty($totalAdmin)) {
                    DB::rollback();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Admin outlet is empty']
                    ]);
                }

                $link = config('url.app_url').'/transaction/admin/'.$insertTransaction['transaction_receipt_number'].'/'.$totalAdmin['phone'];
            }
            $order_id = MyHelper::createrandom(4, 'Besar Angka');

            //cek unique order id today
            $cekOrderId = TransactionPickup::join('transactions', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
                                            ->where('id_outlet', $insertTransaction['id_outlet'])
                                            ->where('order_id', $order_id)
                                            ->whereDate('transaction_date', date('Y-m-d'))
                                            ->first();
            while($cekOrderId){
                $order_id = MyHelper::createrandom(4, 'Besar Angka');

                $cekOrderId = TransactionPickup::join('transactions', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
                                                ->where('id_outlet', $insertTransaction['id_outlet'])
                                                ->where('order_id', $order_id)
                                                ->whereDate('transaction_date', date('Y-m-d'))
                                                ->first();
            }

            //cek unique order id today
            $cekOrderId = TransactionPickup::join('transactions', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
                                            ->where('id_outlet', $insertTransaction['id_outlet'])
                                            ->where('order_id', $order_id)
                                            ->whereDate('transaction_date', date('Y-m-d'))
                                            ->first();
            while ($cekOrderId) {
                $order_id = MyHelper::createrandom(4, 'Besar Angka');

                $cekOrderId = TransactionPickup::join('transactions', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
                                                ->where('id_outlet', $insertTransaction['id_outlet'])
                                                ->where('order_id', $order_id)
                                                ->whereDate('transaction_date', date('Y-m-d'))
                                                ->first();
            }

            if (isset($post['taken_at']) && $post['taken_at']) {
                $post['taken_at'] = date('Y-m-d H:i:s', strtotime($post['taken_at']));
            } else {
                $post['taken_at'] = null;
            }

            if (isset($post['id_admin_outlet_taken'])) {
                $post['id_admin_outlet_taken'] = $post['id_admin_outlet_taken'];
            } else {
                $post['id_admin_outlet_taken'] = null;
            }

            if(isset($post['pickup_type'])){
                $pickupType = $post['pickup_type'];
            }elseif($post['type'] == 'GO-SEND'){
                $pickupType = 'right now';
            }else{
                $pickupType = 'set time';
            }

            if($pickupType == 'set time'){
                $settingTime = Setting::where('key', 'processing_time')->first();
                if (date('Y-m-d H:i:s', strtotime($post['pickup_at'])) <= date('Y-m-d H:i:s', strtotime('- '.$settingTime['value'].'minutes'))) {
                    $pickup = date('Y-m-d H:i:s', strtotime('+ '.$settingTime['value'].'minutes'));
                }
                else {
                    if(isset($outlet['today']['close'])){
                        if(date('Y-m-d H:i', strtotime($post['pickup_at'])) > date('Y-m-d').' '.date('H:i', strtotime($outlet['today']['close']))){
                            $pickup =  date('Y-m-d').' '.date('H:i:s', strtotime($outlet['today']['close']));
                        }else{
                            $pickup = date('Y-m-d H:i:s', strtotime($post['pickup_at']));
                        }
                    }else{
                        $pickup = date('Y-m-d H:i:s', strtotime($post['pickup_at']));
                    }
                }
            }else{
                $pickup = null;
            }

            $dataPickup = [
                'id_transaction'          => $insertTransaction['id_transaction'],
                'order_id'                => $order_id,
                'short_link'              => config('url.app_url').'/transaction/'.$order_id.'/status',
                'pickup_type'             => $pickupType,
                'pickup_at'               => $pickup,
                'receive_at'              => $post['receive_at'],
                'taken_at'                => $post['taken_at'],
                'id_admin_outlet_receive' => $post['id_admin_outlet_receive'],
                'id_admin_outlet_taken'   => $post['id_admin_outlet_taken'],
                'short_link'              => $link
            ];

            if($post['type'] == 'GO-SEND'){
                $dataPickup['pickup_by'] = 'GO-SEND';
            }else{
                $dataPickup['pickup_by'] = 'Customer';
            }

            $insertPickup = TransactionPickup::create($dataPickup);

            if (!$insertPickup) {
                DB::rollback();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Pickup Order Transaction Failed']
                ]);
            }
            if($dataPickup['taken_at']){
                Transaction::where('id_transaction',$dataPickup['id_transaction'])->update(['show_rate_popup'=>1]);
            }
            //insert pickup go-send
            if($post['type'] == 'GO-SEND'){
                if (!($post['destination']['short_address']??false)) {
                    $post['destination']['short_address'] = $post['destination']['address'];
                }

                $dataGoSend['id_transaction_pickup'] = $insertPickup['id_transaction_pickup'];
                $dataGoSend['origin_name']           = $outlet['outlet_name'];
                $dataGoSend['origin_phone']          = $outlet['outlet_phone'];
                $dataGoSend['origin_address']        = $outlet['outlet_address'];
                $dataGoSend['origin_latitude']       = $outlet['outlet_latitude'];
                $dataGoSend['origin_longitude']      = $outlet['outlet_longitude'];
                $dataGoSend['origin_note']           = "NOTE: bila ada pertanyaan, mohon hubungi penerima terlebih dahulu untuk informasi. \nPickup Code $order_id";
                $dataGoSend['destination_name']      = $user['name'];
                $dataGoSend['destination_phone']     = $user['phone'];
                $dataGoSend['destination_address']   = $post['destination']['address'];
                $dataGoSend['destination_short_address'] = $post['destination']['short_address'];
                $dataGoSend['destination_address_name']   = $addressx->name;
                $dataGoSend['destination_latitude']  = $post['destination']['latitude'];
                $dataGoSend['destination_longitude'] = $post['destination']['longitude'];

                if(isset($post['destination']['description'])){
                    $dataGoSend['destination_note'] = $post['destination']['description'];
                }

                $gosend = TransactionPickupGoSend::create($dataGoSend);
                if (!$gosend) {
                    DB::rollback();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Insert Transaction GO-SEND Failed']
                    ]);
                }

                $id_pickup_go_send = $gosend->id_transaction_pickup_go_send;
            }
        }

        if ($post['transaction_payment_status'] == 'Completed') {
            $checkMembership = app($this->membership)->calculateMembership($user['phone']);
            if (!$checkMembership) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Recount membership failed']
                ]);
            }
        }

        if (isset($post['payment_type']) || $insertTransaction['transaction_grandtotal'] == 0) {

            if ($post['payment_type'] == 'Balance'  || $insertTransaction['transaction_grandtotal'] == 0) {

                if($insertTransaction['transaction_grandtotal'] > 0){
                    $save = app($this->balance)->topUp($insertTransaction['id_user'], $insertTransaction['transaction_grandtotal'], $insertTransaction['id_transaction']);

                    if (!isset($save['status'])) {
                        DB::rollBack();
                        return response()->json(['status' => 'fail', 'messages' => ['Transaction failed']]);
                    }

                    if ($save['status'] == 'fail') {
                        DB::rollBack();
                        return response()->json($save);
                    }
                }else{
                    $save['status'] = 'success'; 
                    $save['type'] = 'no_topup';

                    $pickup = TransactionPickup::where('id_transaction', $insertTransaction['id_transaction'])->first();
                    if ($pickup) {
                        if ($pickup->pickup_by == 'Customer') {
                            \App\Lib\ConnectPOS::create()->sendTransaction($insertTransaction['id_transaction']);
                        } else {
                            $pickup->bookDelivery();
                        }
                    }
                }

                if ($post['transaction_payment_status'] == 'Completed' || $save['type'] == 'no_topup') {

                    if($config_fraud_use_queue == 1){
                        FraudJob::dispatch($user, $insertTransaction, 'transaction')->onConnection('fraudqueue');
                    }else {
                        if($config_fraud_use_queue != 1){
                            $checkFraud = app($this->setting_fraud)->checkFraudTrxOnline($user, $insertTransaction);
                        }
                    }

                    //inset pickup_at when pickup_type = right now
                    if($insertPickup['pickup_type'] == 'right now'){
                        $settingTime = MyHelper::setting('processing_time', 'value', 0);
                        $updatePickup = TransactionPickup::where('id_transaction', $insertTransaction['id_transaction'])->update(['pickup_at' => date('Y-m-d H:i:s', strtotime('+ '.$settingTime.'minutes')?:time())]);
                    }

                    $mid['order_id'] = $insertTransaction['transaction_receipt_number'];
                    $mid['gross_amount'] = 0;

                    $insertTransaction = Transaction::with('user.memberships', 'outlet', 'productTransaction')->where('transaction_receipt_number', $insertTransaction['transaction_receipt_number'])->first();

                    if($request->json('id_deals_user') && !$request->json('promo_code'))
			        {
			        	$check_trx_voucher = TransactionVoucher::where('id_deals_voucher', $deals['id_deals_voucher'])->where('status','success')->count();

						if(($check_trx_voucher??false) > 1)
						{
							DB::rollBack();
				            return [
				                'status'=>'fail',
				                'messages'=>['Voucher is not valid']
				            ];
				        }
			        }


                    if ($configAdminOutlet && $configAdminOutlet['is_active'] == '1') {
                        $sendAdmin = app($this->notif)->sendNotif($insertTransaction);
                        if (!$sendAdmin) {
                            DB::rollBack();
                            return response()->json([
                                'status'    => 'fail',
                                'messages'  => ['Transaction failed']
                            ]);
                        }
                    }

                    $send = app($this->notif)->notification($mid, $insertTransaction);

                    if (!$send) {
                        DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'  => ['Transaction failed']
                        ]);
                    }

                    $sendNotifOutlet = $this->outletNotif($insertTransaction['id_transaction']);
                    // return $sendNotifOutlet;
                    $dataRedirect = $this->dataRedirect($insertTransaction['transaction_receipt_number'], 'trx', '1');

                    if($post['latitude'] && $post['longitude']){
                        $savelocation = $this->saveLocation($post['latitude'], $post['longitude'], $insertTransaction['id_user'], $insertTransaction['id_transaction'], $insertTransaction['id_outlet']);
                     }

                    // PromoCampaignTools::applyReferrerCashback($insertTransaction);

                    DB::commit();

                    /* Add daily Trx*/
                    $dataDailyTrx = [
                        'id_transaction'    => $insertTransaction['id_transaction'],
                        'id_outlet'         => $outlet['id_outlet'],
                        'transaction_date'  => date('Y-m-d H:i:s', strtotime($insertTransaction['transaction_date'])),
                        'referral_code_use_date'=> date('Y-m-d H:i:s', strtotime($insertTransaction['transaction_date'])),
                        'id_user'           => $user['id'],
                        'referral_code'     => NULL
                    ];
                    $createDailyTrx = DailyTransactions::create($dataDailyTrx);

                    /* Fraud Referral*/
                    if($promo_code_ref){
                        //======= Start Check Fraud Referral User =======//
                        $data = [
                            'id_user' => $insertTransaction['id_user'],
                            'referral_code' => $promo_code_ref,
                            'referral_code_use_date' => $insertTransaction['transaction_date'],
                            'id_transaction' => $insertTransaction['id_transaction']
                        ];
                        if($config_fraud_use_queue == 1){
                            FraudJob::dispatch($user, $data, 'referral user')->onConnection('fraudqueue');
                            FraudJob::dispatch($user, $data, 'referral')->onConnection('fraudqueue');
                        }else{
                            app($this->setting_fraud)->fraudCheckReferralUser($data);
                            app($this->setting_fraud)->fraudCheckReferral($data);
                        }
                        //======= End Check Fraud Referral User =======//
                    }
                    FraudJob::dispatch($insertTransaction['id_user'], [], 'transaction_in_between')->onConnection('fraudqueue');

                    //remove for result
                    unset($insertTransaction['user']);
                    unset($insertTransaction['outlet']);
                    unset($insertTransaction['product_transaction']);

                    return response()->json([
                        'status'     => 'success',
                        'redirect'   => false,
                        'result'     => $insertTransaction,
                        'additional' => $dataRedirect
                    ]);
                }
            }

            if ($post['payment_type'] == 'Midtrans') {
                if ($post['transaction_payment_status'] == 'Completed') {
                    //bank
                    $bank = ['BNI', 'Mandiri', 'BCA'];
                    $getBank = array_rand($bank);

                    //payment_method
                    $method = ['credit_card', 'bank_transfer', 'direct_debit'];
                    $getMethod = array_rand($method);

                    $dataInsertMidtrans = [
                        'id_transaction'     => $insertTransaction['id_transaction'],
                        'approval_code'      => 000000,
                        'bank'               => $bank[$getBank],
                        'eci'                => $this->getrandomnumber(2),
                        'transaction_time'   => $insertTransaction['transaction_date'],
                        'gross_amount'       => $insertTransaction['transaction_grandtotal'],
                        'order_id'           => $insertTransaction['transaction_receipt_number'],
                        'payment_type'       => $method[$getMethod],
                        'signature_key'      => $this->getrandomstring(),
                        'status_code'        => 200,
                        'vt_transaction_id'  => $this->getrandomstring(8).'-'.$this->getrandomstring(4).'-'.$this->getrandomstring(4).'-'.$this->getrandomstring(12),
                        'transaction_status' => 'capture',
                        'fraud_status'       => 'accept',
                        'status_message'     => 'Veritrans payment notification'
                    ];

                    $insertDataMidtrans = TransactionPaymentMidtran::create($dataInsertMidtrans);
                    if (!$insertDataMidtrans) {
                        DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'  => ['Insert Data Midtrans Failed']
                        ]);
                    }

                    // if($post['latitude'] && $post['longitude']){
                    //     $savelocation = $this->saveLocation($post['latitude'], $post['longitude'], $insertTransaction['id_user'], $insertTransaction['id_transaction']);
                    // }

                }

            }
        }

        // if($post['latitude'] && $post['longitude']){
        //    $savelocation = $this->saveLocation($post['latitude'], $post['longitude'], $insertTransaction['id_user'], $insertTransaction['id_transaction']);
        // }

        if($request->json('id_deals_user') && !$request->json('promo_code'))
        {
        	$check_trx_voucher = TransactionVoucher::where('id_deals_voucher', $deals['id_deals_voucher'])->where('status','success')->count();

			if(($check_trx_voucher??false) > 1)
			{
				DB::rollBack();
	            return [
	                'status'=>'fail',
	                'messages'=>['Voucher is not valid']
	            ];
	        }
        }
        if (!empty($data_autocrm_cashback)) {
	        $send   = app($this->autocrm)->SendAutoCRM('Transaction Point Achievement', $usere->phone,$data_autocrm_cashback);
	        if($send != true){
	            DB::rollBack();
	            return response()->json([
	                'status' => 'fail',
	                'messages' => ['Failed Send notification to customer']
	            ]);
	        }
        }

        DB::commit();
        /* Add daily Trx*/
        $dataDailyTrx = [
            'id_transaction'    => $insertTransaction['id_transaction'],
            'id_outlet'         => $outlet['id_outlet'],
            'transaction_date'  => date('Y-m-d H:i:s', strtotime($insertTransaction['transaction_date'])),
            'referral_code_use_date'=> date('Y-m-d H:i:s', strtotime($insertTransaction['transaction_date'])),
            'id_user'           => $user['id'],
            'referral_code'     => NULL
        ];
        $createDailyTrx = DailyTransactions::create($dataDailyTrx);

        /* Fraud Referral*/
        if($promo_code_ref){
            //======= Start Check Fraud Referral User =======//
            $data = [
                'id_user' => $insertTransaction['id_user'],
                'referral_code' => $promo_code_ref,
                'referral_code_use_date' => $insertTransaction['transaction_date'],
                'id_transaction' => $insertTransaction['id_transaction']
            ];
            if($config_fraud_use_queue == 1){
                FraudJob::dispatch($user, $data, 'referral user')->onConnection('fraudqueue');
                FraudJob::dispatch($user, $data, 'referral')->onConnection('fraudqueue');
            }else{
                app($this->setting_fraud)->fraudCheckReferralUser($data);
                app($this->setting_fraud)->fraudCheckReferral($data);
            }
            //======= End Check Fraud Referral User =======//
        }
        FraudJob::dispatch($insertTransaction['id_user'], [], 'transaction_in_between')->onConnection('fraudqueue');

        $insertTransaction['cancel_message'] = 'Are you sure you want to cancel this transaction?';

        $getSettingTimer = Setting::where('key', 'setting_timer_ovo')->first();
        $insertTransaction['timer_shopeepay'] = (int) MyHelper::setting('shopeepay_validity_period','value', 300);
        if($getSettingTimer){
            $insertTransaction['timer_ovo'] = (int)$getSettingTimer['value'];
            // $insertTransaction['message_timeout_ovo'] = "You have ".(int)$getSettingTimer['value']." seconds remaning to complete the payment";
        }else{
            $insertTransaction['timer_ovo'] = NULL;
            // $insertTransaction['message_timeout_ovo'] = "You have 0 seconds remaning to complete the payment";
        }
        $insertTransaction['message_timeout_shopeepay'] = "Sorry, your payment has expired";
        $insertTransaction['message_timeout_ovo'] = "Sorry, your payment has expired";
        return response()->json([
            'status'   => 'success',
            'redirect' => true,
            'result'   => $insertTransaction
        ]);

    }

    /**
     * Get info from given cart data
     * @param  CheckTransaction $request [description]
     * @return View                    [description]
     */
    public function checkTransaction(CheckTransaction $request) {
        $post = $request->json()->all();
        $missing_product = 0;
        $clear_cart = 0;
        $error_msg	= [];
        $use_product_variant = \App\Http\Models\Configs::where('id_config',94)->pluck('is_active')->first();
        $post['item'] = $this->mergeProducts($post['item']);
        if($use_product_variant){
            foreach ($post['item'] as $key => &$prod) {
                $prd = Product::where(function($query) use ($prod){
                    foreach($prod['variants'] as $variant){
                        $query->whereHas('product_variants',function($query) use ($variant){
                            $query->where('product_variants.id_product_variant',$variant);
                        });
                    }
                })->where('id_product_group',$prod['id_product_group'])->first();
                if(!$prd){
                    $missing_product++;
                    unset($post['item'][$key]);
                }
                $prod['id_product'] = $prd['id_product'];
            }
        }

        $grandTotal = app($this->setting_trx)->grandTotal();
        $user = $request->user();
        //Check Outlet
        $id_outlet = $post['id_outlet'];
        $outlet = Outlet::where('id_outlet', $id_outlet)->with('today')->first();
        $rn = $request->json('request_number');
        $ovo_available = Ovo::checkOutletOvo($post['id_outlet']);
        if (empty($outlet)) {
            DB::rollBack();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Outlet Not Found'],
                'clear_cart'  => 1
            ]);
        }

        $issetDate = false;
        if (isset($post['transaction_date'])) {
            $issetDate = true;
            $post['transaction_date'] = date('Y-m-d H:i:s', strtotime($post['transaction_date']));
        } else {
            $post['transaction_date'] = date('Y-m-d H:i:s');
        }
        $outlet_status = 1;
        //cek outlet active
        if(isset($outlet['outlet_status']) && $outlet['outlet_status'] == 'Inactive'){
            // DB::rollBack();
            // return response()->json([
            //     'status'    => 'fail',
            //     'messages'  => ['Outlet is closed']
            // ]);
            $clear_cart = 1;
            $outlet_status = 0;
        }

        //cek outlet holiday
        if($issetDate == false){
            $holiday = Holiday::join('outlet_holidays', 'holidays.id_holiday', 'outlet_holidays.id_holiday')->join('date_holidays', 'holidays.id_holiday', 'date_holidays.id_holiday')
                    ->where('id_outlet', $outlet['id_outlet'])->whereDay('date_holidays.date', date('d'))->whereMonth('date_holidays.date', date('m'))->get();
            if(count($holiday) > 0){
                foreach($holiday as $i => $holi){
                    if($holi['yearly'] == '0'){
                        if($holi['date'] == date('Y-m-d')){
                            // DB::rollBack();
                            // return response()->json([
                            //     'status'    => 'fail',
                            //     'messages'  => ['Outlet is closed']
                            // ]);
                            $outlet_status = 0;
                        }
                    }else{
                        // DB::rollBack();
                        // return response()->json([
                        //     'status'    => 'fail',
                        //     'messages'  => ['Outlet is closed']
                        // ]);
                        $outlet_status = 0;
                    }
                }
            }

            if($outlet['today']['is_closed'] == '1'){
                // DB::rollBack();
                // return response()->json([
                //     'status'    => 'fail',
                //     'messages'  => ['Outlet is closed']
                // ]);
                $outlet_status = 0;
            }

             if($outlet['today']['close'] && $outlet['today']['open'] ){

                $settingTime = Setting::where('key', 'processing_time')->first();
                if($settingTime && $settingTime->value){
                    if($outlet['today']['close'] && date('H:i') > date('H:i', strtotime('-'.$settingTime->value.' minutes' ,strtotime($outlet['today']['close'])))){
                        // DB::rollBack();
                        // return response()->json([
                        //     'status'    => 'fail',
                        //     'messages'  => ['Outlet is closed']
                        // ]);
                        $outlet_status = 0;
                    }
                }

                //cek outlet open - close hour
                if(($outlet['today']['open'] && date('H:i') < date('H:i', strtotime($outlet['today']['open']))) || ($outlet['today']['close'] && date('H:i') > date('H:i', strtotime($outlet['today']['close'])))){
                    // DB::rollBack();
                    // return response()->json([
                    //     'status'    => 'fail',
                    //     'messages'  => ['Outlet is closed']
                    // ]);
                    $outlet_status = 0;
                }
            }
        }

        if (!isset($post['payment_type'])) {
            $post['payment_type'] = null;
        }

        if (!isset($post['shipping'])) {
            $post['shipping'] = 0;
        }

        $shippingGoSend = 0;

        $error_msg=[];

        if(($post['type'] ?? null) == 'GO-SEND' && !$outlet->delivery_order) {
            $error_msg[] = 'Maaf, Outlet ini tidak support untuk delivery order';
        }

        if(($post['type']??null) == 'GO-SEND'){
            if(!($outlet['outlet_latitude']&&$outlet['outlet_longitude']&&$outlet['outlet_phone']&&$outlet['outlet_address'])){
                app($this->outlet)->sendNotifIncompleteOutlet($outlet['id_outlet']);
                $outlet->notify_admin = 1;
                $outlet->save();
                return [
                    'status' => 'fail',
                    'messages' => ['Tidak dapat melakukan pengiriman dari outlet ini']
                ];
            }
            $coor_origin = [
                'latitude' => number_format($outlet['outlet_latitude'],8),
                'longitude' => number_format($outlet['outlet_longitude'],8)
            ];
            $coor_destination = [
                'latitude' => number_format($post['destination']['latitude'],8),
                'longitude' => number_format($post['destination']['longitude'],8)
            ];
            $type = 'Pickup Order';
            $shippingGoSendx = GoSend::getPrice($coor_origin,$coor_destination);
            $shippingGoSend = $shippingGoSendx[GoSend::getShipmentMethod()]['price']['total_price']??null;
            if($shippingGoSend === null){
                $error_msg += array_column($shippingGoSendx[GoSend::getShipmentMethod()]['errors']??[],'message')?:['Gagal menghitung ongkos kirim'];
            } else {
                $post['shipping'] = $shippingGoSend;
            }
            //cek free delivery
            // if($post['is_free'] == 'yes'){
            //     $isFree = '1';
            // }
            $isFree = 0;
        }

        if (!isset($post['subtotal'])) {
            $post['subtotal'] = 0;
        }

        if (!isset($post['discount'])) {
            $post['discount'] = 0;
        }

        if (!isset($post['service'])) {
            $post['service'] = 0;
        }

        if (!isset($post['tax'])) {
            $post['tax'] = 0;
        }

        $post['discount'] = -$post['discount'];

        // hitung product discount
        $totalDisProduct = 0;
        $productDis = app($this->setting_trx)->discountProduct($post);
        if (is_numeric($productDis)) {
            $totalDisProduct = $productDis;
        }else{
            $error_msg[] = $productDis['messages'];
        }

        // remove bonus item
        $pct = new PromoCampaignTools();
        $post['item'] = $pct->removeBonusItem($post['item']);

        // check promo code
        $promo_error=null;
        $use_referral = false;
        $promo['description']=null;
        $promo['detail']=null;
        $promo['type']=null;
        $promo['value']=0;
        $promo['discount']=0;
        $promo_source = null;
        if($request->json('promo_code'))
        {
        	$code = app($this->promo_campaign)->checkPromoCode($request->promo_code, 1, 1);
            if ($code)
            {
	        	if ($code['promo_campaign']['date_end'] < date('Y-m-d H:i:s')) {
	        		$error = ['Promo campaign is ended'];
            		$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
	        	}
	        	else
	        	{
                    $post['id_promo_campaign_promo_code'] = $code->id_promo_campaign_promo_code;
                    if($code->promo_type == "Referral"){
                        $promo_code_ref = $request->json('promo_code');
                        $use_referral = true;
                    }
		            $pct=new PromoCampaignTools();
		            $validate_user=$pct->validateUser($code->id_promo_campaign, $request->user()->id, $request->user()->phone, $request->device_type, $request->device_id, $errore,$code->id_promo_campaign_promo_code);

		            if ($validate_user) {
			            $discount_promo=$pct->validatePromo($code->id_promo_campaign, $request->id_outlet, $post['item'], $errors, 'promo_campaign', $post['payment_type'], $error_product);


			            // if (isset($discount_promo['is_free']) && $discount_promo['is_free'] == 1) {
			            // 	// unset($discount_promo['item']);
			            // 	$discount_promo['discount'] = 0;
			            // }
			            $discount_type 			= $code->promo_campaign->promo_type;
			            $promo['description']	= $discount_promo['new_description'];
			            $promo['detail'] 		= $discount_promo['promo_detail'];
			            $promo['discount'] 		= $discount_promo['discount'];
			            $promo['value'] 		= $discount_promo['discount'];
			            $promo['is_free'] 		= $discount_promo['is_free'];
			            $promo['type'] 			= 'discount';
			            $promo_source 			= 'promo_code';

			            if ($code['promo_type'] == 'Referral') 
			            {
			            	$code->load('promo_campaign_referral');
				            if ($code->promo_campaign_referral->referred_promo_type == 'Cashback') 
				            {
				            	$promo['type'] = 'cashback';
				            	$promo['detail'] = 'Referral (Cashback)';
				            }
			            }
			            if ( !empty($errore) || !empty($errors)) {
			            	$promo_error = app($this->promo_campaign)->promoError('transaction', $errore, $errors, $error_product);
			            	$promo_error['product_label'] = app($this->promo_campaign)->getProduct('promo_campaign', $code['promo_campaign'])['product']??'';
			            	$promo_error['warning_image'] = env('S3_URL_API').($code['promo_campaign_warning_image']??$promo_error['warning_image']);
					        $promo_error['product'] = $pct->getRequiredProduct($code->id_promo_campaign)??null;
			            	$promo_source = null;

			            }
		            	$promo_discount=$discount_promo['discount'];
		            }else{
		            	if(!empty($errore)){
		            		$promo_error = app($this->promo_campaign)->promoError('transaction', $errore);
		            	}
		            }
	        	}
            }
            else
            {
            	$error = ['Promo code invalid'];
            	$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
            }
        }
        elseif($request->json('id_deals_user'))
        {
	        $deals = DealsUser::whereIn('paid_status', ['Free', 'Completed'])->where('id_deals_user', $request->id_deals_user)->first();

	        if (!$deals){
	        	$error = ['Voucher is not found'];
	        	$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
	        }elseif( !empty($deals['used_at']) ){
	        	$error = ['Voucher already used'];
	        	$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
	        }elseif( date('Y-m-d H:i:s', strtotime($deals['voucher_expired_at'])) < date('Y-m-d H:i:s') ){
	        	$error = ['Voucher is expired'];
	        	$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
	        }elseif( !empty($deals['voucher_active_at']) && date('Y-m-d H:i:s', strtotime($deals['voucher_active_at'])) > date('Y-m-d H:i:s') ){
	        	$error = ['Voucher periode hasn\'t started'];
	        	$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
	        }elseif($deals){
				$validate_user = true;
				$pct = new PromoCampaignTools();
				$discount_promo=$pct->validatePromo($deals->dealVoucher->id_deals, $request->id_outlet, $post['item'], $errors, 'deals', null, $error_product);

				/*if ($discount_promo['is_free'] == 1) {
	            	// unset($discount_promo['item']);
	            	$discount_promo['discount'] = 0;
	            }*/
	            $discount_type = $deals->dealVoucher->deals->promo_type;
				$promo['description'] = $discount_promo['new_description'];
	            $promo['detail'] = $discount_promo['promo_detail'];
	            $promo['discount'] = $discount_promo['discount'];
	            $promo['value'] = $discount_promo['discount'];
	            $promo['is_free'] = $discount_promo['is_free'];
	            $promo['type'] = 'discount';
		        $promo_source = 'voucher_online';

				if ( !empty($errors) ) {
					$code_obj = $deals;
					$code = $deals->toArray();

	            	$promo_error = app($this->promo_campaign)->promoError('transaction', null, $errors, $error_product);
	            	$promo_error['product_label'] = app($this->promo_campaign)->getProduct('deals', $code_obj['dealVoucher']['deals'])['product']??'';
	            	$promo_error['warning_image'] = env('S3_URL_API').($code['deal_voucher']['deals']['deals_warning_image']??$promo_error['warning_image']);
		        	$promo_error['product'] = $pct->getRequiredProduct($deals->dealVoucher->id_deals, 'deals')??null;
		        	$promo_source = null;
	            }
	            $promo_discount=$discount_promo['discount'];
	        }
	        else
	        {
	        	$error = ['Voucher is not valid'];
	        	$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
	        }
        }
        // end check promo code

        if (empty($request->json('id_deals_user')) && empty($request->json('promo_code'))) {
        	$promo = null;
        }

        $tree = [];
        // check and group product
        $subtotal = 0;
        $product_promo = 0;
        $product_promo_sold_out = 0;
        $remove_promo = 0;
        foreach ($discount_promo['item']??$post['item'] as &$item) {

        	if ($item['is_promo'] ?? false) {
        		$product_promo++;
        	}

        	if ($product_promo_sold_out != 0 && $item['bonus'] ?? false) {

				$discount_promo['item'] = $post['item'];
				$remove_promo = 1;
        		continue;
        	}

            // get detail product
            if($use_product_variant){
                $select = [
                    'products.id_product',
                    'products.product_code','product_groups.product_group_name as product_name','product_groups.product_group_description as product_description',
                    'product_prices.product_price','product_prices.product_stock_status',
                    'brand_product.id_product_category','brand_product.id_brand'
                ];
            }else{
                $select = [
                    'products.id_product',
                    'products.product_code','products.product_name','products.product_description',
                    'product_prices.product_price','product_prices.product_stock_status',
                    'brand_product.id_product_category','brand_product.id_brand'
                ];
            }

            $product = Product::select($select)
            ->join('brand_product','brand_product.id_product','=','products.id_product')
            // produk tersedia di outlet
            ->join('product_prices','product_prices.id_product','=','products.id_product')
            ->where('product_prices.id_outlet','=',$outlet->id_outlet)
            // brand produk ada di outlet
            ->where('brand_outlet.id_outlet','=',$outlet->id_outlet)
            ->join('brand_outlet','brand_outlet.id_brand','=','brand_product.id_brand')
            // produk ada di brand ini
            ->where('brand_product.id_brand',$item['id_brand'])
            ->where(function($query){
                $query->where('product_prices.product_visibility','=','Visible')
                        ->orWhere(function($q){
                            $q->whereNull('product_prices.product_visibility')
                            ->where('products.product_visibility', 'Visible');
                        });
            })
            ->where('product_prices.product_status','=','Active')
            ->whereNotNull('product_prices.product_price')
            ->with([
                'photos'=>function($query){
                    $query->select('id_product','product_photo');
                }
            ])
            ->groupBy('products.id_product')
            ->orderBy('products.position');

            if($use_product_variant){
                $product->join('product_groups','product_groups.id_product_group','=','products.id_product_group');
                $product->addSelect(DB::raw('products.id_product_group,product_group_code'));
            }

            $product = $product->find($item['id_product']);
            if(!$product){
                $missing_product++;
                continue;
            }
            $product->append('photo');
            $product = $product->toArray();
            if($use_product_variant){
                $variants = $item['variants'];
                $product['variants'] = [];
                foreach ($variants as $variant) {
                    $product['variants'][] = ProductVariant::select('id_product_variant','product_variant_code','product_variant_name')->find($variant);
                }
            }
            if($product['product_stock_status']!='Available'){

            	if ($item['is_promo'] ?? false) {
	        		$product_promo_sold_out++;
	        	}
	        	if ($item['bonus'] ?? false) {
	        		$remove_promo = 1;
	        	}

                $error_msg[] = MyHelper::simpleReplace(
                    '%product_name% is out of stock',
                    [
                        'product_name' => $product['product_name']
                    ]
                );
                continue;
            }
            unset($product['photos']);
            $product['id_custom'] = $item['id_custom']??null;
            $product['qty'] = $item['qty'];
            $product['note'] = $item['note']??'';
            $product['promo_discount'] = $item['discount']??0;
            $product['is_promo'] = $item['is_promo']??0;
            $product['is_free'] = $item['is_free']??0;
            $product['bonus'] = $item['bonus']??0;
            // get modifier
            $mod_price = 0;
            $product['modifiers'] = [];
            $removed_modifier = [];
            $missing_modifier = 0;
            foreach ($item['modifiers'] as $key => $modifier) {
                $id_product_modifier = is_numeric($modifier)?$modifier:$modifier['id_product_modifier'];
                $qty_product_modifier = is_numeric($modifier)?1:$modifier['qty'];
                $mod = ProductModifier::select('product_modifiers.id_product_modifier','code','text','product_modifier_stock_status','product_modifier_price')
                    // produk modifier yang tersedia di outlet
                    ->join('product_modifier_prices','product_modifiers.id_product_modifier','=','product_modifier_prices.id_product_modifier')
                    ->where('product_modifier_prices.id_outlet',$id_outlet)
                    // produk aktif
                    ->where('product_modifier_status','Active')
                    // product visible
                    ->where(function($query){
                        $query->where('product_modifier_prices.product_modifier_visibility','=','Visible')
                        ->orWhere(function($q){
                            $q->whereNull('product_modifier_prices.product_modifier_visibility')
                            ->where('product_modifiers.product_modifier_visibility', 'Visible');
                        });
                    })
                    ->groupBy('product_modifiers.id_product_modifier')
                    // product modifier dengan id
                    ->find($id_product_modifier);
                if(!$mod){
                    $missing_modifier++;
                    continue;
                }
                if($mod['product_modifier_stock_status']!='Available'){
                    $removed_modifier[] = $mod['text'];
                    continue;
                }
                $mod = $mod->toArray();
                $mod['product_modifier_price_pretty'] = MyHelper::requestNumber($mod['product_modifier_price'],'_CURRENCY');
                $mod['product_modifier_price'] = (float) $mod['product_modifier_price'];
                $mod['qty'] = $qty_product_modifier;
                $mod_price+=$mod['qty']*$mod['product_modifier_price'];
                $mod['product_modifier_price'] = MyHelper::requestNumber($mod['product_modifier_price'],$rn);
                $product['modifiers'][]=$mod;
            }
            if($missing_modifier){
                $error_msg[] = MyHelper::simpleReplace(
                    '%missing_modifier% modifiers for product %product_name% not found',
                    [
                        'missing_modifier' => $missing_modifier,
                        'product_name' => $product['product_name']
                    ]
                );
            }
            if($removed_modifier){
                $error_msg[] = MyHelper::simpleReplace(
                    'Modifier %removed_modifier% for product %product_name% is out of stock',
                    [
                        'removed_modifier' => implode(',',$removed_modifier),
                        'product_name' => $product['product_name']
                    ]
                );
            }
            if(!isset($tree[$product['id_brand']]['name_brand'])){
                $tree[$product['id_brand']] = Brand::select('name_brand','id_brand')->find($product['id_brand'])->toArray();
            }

            $product_price_total = $product['qty'] * ($product['product_price']+$mod_price);
            $product['product_price_total_pretty'] = MyHelper::requestNumber($product_price_total,'_CURRENCY');
            $product['product_price_divider_pretty'] = MyHelper::requestNumber($product['product_price']+$mod_price,'_CURRENCY');
            $product['product_price_pretty'] = MyHelper::requestNumber($product['product_price'],'_CURRENCY');
            $product['product_price_total'] = MyHelper::requestNumber($product_price_total,$rn);
            $product['product_price'] = MyHelper::requestNumber($product['product_price'],$rn);

            $tree[$product['id_brand']]['products'][]=$product;
            $subtotal += $product_price_total;
        }
        if ($validate_user??false) {
	        if ( (!empty($product_promo) && !empty($product_promo_sold_out) && $product_promo == $product_promo_sold_out) || $remove_promo == 1 ) {
	        	$discount_promo['item'] = $post['item'];
	        	$promo_error = null;
	        	$promo = null;
	        }
	        elseif(($discount_type??false) == 'Product discount' && $product_promo > $product_promo_sold_out){
	        	$promo_error = null;
	        }
        }
        if($missing_product){
            $error_msg[] = MyHelper::simpleReplace(
                '%missing_product% products not found',
                [
                    'missing_product' => $missing_product
                ]
            );
        }
        foreach ($grandTotal as $keyTotal => $valueTotal) {
            if ($valueTotal == 'subtotal') {
                $post['subtotal'] = $subtotal - $totalDisProduct;
            } elseif ($valueTotal == 'discount') {
                // $post['dis'] = $this->countTransaction($valueTotal, $post);
                $post['dis'] = app($this->setting_trx)->countTransaction($valueTotal, $post, $discount_promo??[]);
                $mes = ['Data Not Valid'];

                if (isset($post['dis']->original['messages'])) {
                    $mes = $post['dis']->original['messages'];

                    if ($post['dis']->original['messages'] == ['Price Product Not Found']) {
                        if (isset($post['dis']->original['product'])) {
                            $mes = ['Price Product Not Found with product '.$post['dis']->original['product'].' at outlet '.$outlet['outlet_name']];
                        }
                    }

                    $error_msg[] = $mes;
                    /*DB::rollBack();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => $mes
                    ]);*/
                }

                $post['discount'] = $post['dis'] + $totalDisProduct;
            }elseif($valueTotal == 'tax'){
                $post['tax'] = app($this->setting_trx)->countTransaction($valueTotal, $post);
                $mes = ['Data Not Valid'];

                    if (isset($post['tax']->original['messages'])) {
                        $mes = $post['tax']->original['messages'];

                        if ($post['tax']->original['messages'] == ['Price Product Not Found']) {
                            if (isset($post['tax']->original['product'])) {
                                $mes = ['Price Product Not Found with product '.$post['tax']->original['product'].' at outlet '.$outlet['outlet_name']];
                            }
                        }

                        if ($post['sub']->original['messages'] == ['Price Product Not Valid']) {
                            if (isset($post['tax']->original['product'])) {
                                $mes = ['Price Product Not Valid with product '.$post['tax']->original['product'].' at outlet '.$outlet['outlet_name']];
                            }
                        }

                        $error_msg[] = $mes;
                        /*DB::rollBack();
                        return response()->json([
                            'status'    => 'fail',
                            'messages'  => $mes
                        ]);*/
                    }
            }
            else {
                $post[$valueTotal] = app($this->setting_trx)->countTransaction($valueTotal, $post);
            }
        }
        // $post['discount'] = $post['discount'] + ($promo_discount??0);

        $post['cashback'] = app($this->setting_trx)->countTransaction('cashback', $post);

        //count some trx user
        $countUserTrx = Transaction::where('id_user', $user->id)->where('transaction_payment_status', 'Completed')->count();

        $countSettingCashback = TransactionSetting::get();

        // return $countSettingCashback;
        if ($countUserTrx < count($countSettingCashback)) {
            // return $countUserTrx;
            $post['cashback'] = $post['cashback'] * $countSettingCashback[$countUserTrx]['cashback_percent'] / 100;

            if ($post['cashback'] > $countSettingCashback[$countUserTrx]['cashback_maximum']) {
                $post['cashback'] = $countSettingCashback[$countUserTrx]['cashback_maximum'];
            }
        } else {

            $maxCash = Setting::where('key', 'cashback_maximum')->first();

            if (count($user['memberships']) > 0) {
                $post['cashback'] = $post['cashback'] * ($user['memberships'][0]['benefit_cashback_multiplier']) / 100;

                if($user['memberships'][0]['cashback_maximum']){
                    $maxCash['value'] = $user['memberships'][0]['cashback_maximum'];
                }
            }

            $statusCashMax = 'no';

            if (!empty($maxCash) && !empty($maxCash['value'])) {
                $statusCashMax = 'yes';
                $totalCashMax = $maxCash['value'];
            }

            if ($statusCashMax == 'yes') {
                if ($totalCashMax < $post['cashback']) {
                    $post['cashback'] = $totalCashMax;
                }
            } else {
                $post['cashback'] = $post['cashback'];
            }
        }
        $post['cashback'] = MyHelper::requestNumber($post['cashback'],'point');

        // apply cashback
        if ($use_referral){
            $referral_rule = PromoCampaignReferral::where('id_promo_campaign',$code->id_promo_campaign)->first();
            if(!$referral_rule){
            	$error = ['Insert Referrer Cashback Failed'];
            	$promo_error = app($this->promo_campaign)->promoError('transaction', $error);
                /*DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Insert Referrer Cashback Failed']
                ]);*/
            }
            $referred_cashback = 0;
            if($referral_rule->referred_promo_type == 'Cashback'){
                if($referral_rule->referred_promo_unit == 'Percent'){
                    $referred_discount_percent = $referral_rule->referred_promo_value<=100?$referral_rule->referred_promo_value:100;
                    $referred_cashback = $post['subtotal']*$referred_discount_percent/100;
                }else{
                    if($post['subtotal'] >= $referral_rule->referred_min_value){
                        $referred_cashback = $referral_rule->referred_promo_value<=$post['subtotal']?$referral_rule->referred_promo_value:$post['subtotal'];
                    }
                }
            }
            $post['cashback'] = $referred_cashback;
        }

        if (($code['promo_type']??false) == 'Referral') 
        {
            if ($code->promo_campaign_referral->referred_promo_type == 'Cashback') 
            {
            	if ($post['payment_type'] == 'Balance') {
            		$promo['value'] = 0;
            	}
            	else{
            		$promo['value'] = (double) $post['cashback'];
            	}
            }
        }

        $cashback_text = explode('%earned_cashback%',Setting::select('value_text')->where('key', 'earned_cashback_text')->pluck('value_text')->first()?:'You\'ll receive %earned_cashback% for this transaction');
        $cashback_earned = MyHelper::requestNumber($post['cashback'],'point');

        $cashback_text_array = [
            $cashback_text[0],
            count($cashback_text) >= 2?MyHelper::requestNumber($cashback_earned,'_POINT').' Point':'',
            $cashback_text[1]??''
        ];

        $outlet = $outlet->toArray();
        $outlet['today']['status'] = $outlet_status ? 'open' : 'closed';
        $result['outlet'] = [
            'id_outlet' => $outlet['id_outlet'],
            'outlet_code' => $outlet['outlet_code'],
            'outlet_name' => $outlet['outlet_name'],
            'outlet_address' => $outlet['outlet_address'],
            'today' => $outlet['today']
        ];
        $result['item'] = array_values($tree);
        $result['subtotal_pretty'] = MyHelper::requestNumber($subtotal,'_CURRENCY');
        $result['shipping_pretty'] = MyHelper::requestNumber($post['shipping'],'_CURRENCY');
        $result['discount_pretty'] = MyHelper::requestNumber($post['discount'],'_CURRENCY');
        $result['service_pretty'] = MyHelper::requestNumber($post['service'],'_CURRENCY');
        $result['tax_pretty'] = MyHelper::requestNumber($post['tax'],'_CURRENCY');
        $result['subtotal'] = MyHelper::requestNumber($subtotal,$rn);
        $result['shipping'] = MyHelper::requestNumber($post['shipping'],$rn);
        $result['discount'] = MyHelper::requestNumber($post['discount'],$rn);
        $result['service'] = MyHelper::requestNumber($post['service'],$rn);
        $result['tax'] = MyHelper::requestNumber($post['tax'],$rn);
        $grandtotal = $post['subtotal'] + (-$post['discount']) + $post['service'] + $post['tax'] + $post['shipping'];
        $result['grandtotal_pretty'] = MyHelper::requestNumber($grandtotal,'_CURRENCY');
        $result['grandtotal'] = MyHelper::requestNumber($grandtotal,$rn);
        $used_point = 0;
        $result['used_point_pretty'] = MyHelper::requestNumber(0,'_POINT');
        $result['used_point'] = MyHelper::requestNumber(0,$rn);
        $balance = app($this->balance)->balanceNow($user->id);
        $result['points_pretty'] = MyHelper::requestNumber($balance,'_POINT');
        $result['points'] = MyHelper::requestNumber($balance,'point');
        $result['get_point'] = ($post['payment_type'] != 'Balance') ? $this->checkPromoGetPoint($promo_source) : 0;
        if($cashback_earned <= 0){
            $result['get_point'] = 0;
        }
        $result['ovo_available'] = $ovo_available?1:0;
        $result['earned_cashback_text'] = $cashback_text_array;
        if (isset($post['payment_type'])&&$post['payment_type'] == 'Balance') {
            if($balance>=$grandtotal){
                $used_point = $grandtotal;
            }else{
                $used_point = $balance;
                $result['used_point_pretty'] = MyHelper::requestNumber($balance,'_POINT');
                $result['used_point'] = MyHelper::requestNumber($balance,$rn);
            }
            $result['used_point_pretty'] = MyHelper::requestNumber($used_point,'_POINT');
            $result['used_point'] = MyHelper::requestNumber($used_point,$rn);
            $result['points_pretty'] = MyHelper::requestNumber($balance - $used_point,'_POINT');
            $result['points'] = MyHelper::requestNumber(($balance - $used_point),'point');
        }

        $result['total_payment_pretty'] = MyHelper::requestNumber(($grandtotal-$used_point),'_CURRENCY');
        $result['total_payment'] = MyHelper::requestNumber(($grandtotal-$used_point),$rn);

        return MyHelper::checkGet($result)+['messages'=>$error_msg, 'promo_error'=>$promo_error, 'promo'=>$promo, 'clear_cart'=>$clear_cart];
    }

    public function saveLocation($latitude, $longitude, $id_user, $id_transaction, $id_outlet){

        $cek = UserLocationDetail::where('id_reference', $id_transaction)->where('activity', 'Transaction')->first();
        if($cek){
            return true;
        }

        $googlemap = MyHelper::get(env('GEOCODE_URL').$latitude.','.$longitude.'&key='.env('GEOCODE_KEY'));

        if(isset($googlemap['results'][0]['address_components'])){

            $street = null;
            $route = null;
            $level1 = null;
            $level2 = null;
            $level3 = null;
            $level4 = null;
            $level5 = null;
            $country = null;
            $postal = null;
            $address = null;

            foreach($googlemap['results'][0]['address_components'] as $data){
                if($data['types'][0] == 'postal_code'){
                    $postal = $data['long_name'];
                }
                elseif($data['types'][0] == 'route'){
                    $route = $data['long_name'];
                }
                elseif($data['types'][0] == 'administrative_area_level_5'){
                    $level5 = $data['long_name'];
                }
                elseif($data['types'][0] == 'administrative_area_level_4'){
                    $level4 = $data['long_name'];
                }
                elseif($data['types'][0] == 'administrative_area_level_3'){
                    $level3 = $data['long_name'];
                }
                elseif($data['types'][0] == 'administrative_area_level_2'){
                    $level2 = $data['long_name'];
                }
                elseif($data['types'][0] == 'administrative_area_level_1'){
                    $level1 = $data['long_name'];
                }
                elseif($data['types'][0] == 'country'){
                    $country = $data['long_name'];
                }
            }

            if($googlemap['results'][0]['formatted_address']){
                $address = $googlemap['results'][0]['formatted_address'];
            }

            $outletCode = null;
            $outletName = null;

            $outlet = Outlet::find($id_outlet);
            if($outlet){
                $outletCode = $outlet['outlet_code'];
                $outletCode = $outlet['outlet_name'];
            }

            $logactivity = UserLocationDetail::create([
                'id_user' => $id_user,
                'id_reference' => $id_transaction,
                'id_outlet' => $id_outlet,
                'outlet_code' => $outletCode,
                'outlet_name' => $outletName,
                'activity' => 'Transaction',
                'action' => 'Completed',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'response_json' => json_encode($googlemap),
                'route' => $route,
                'street_address' => $street,
                'administrative_area_level_5' => $level5,
                'administrative_area_level_4' => $level4,
                'administrative_area_level_3' => $level3,
                'administrative_area_level_2' => $level2,
                'administrative_area_level_1' => $level1,
                'country' => $country,
                'postal_code' => $postal,
                'formatted_address' => $address
            ]);

            if($logactivity) {
                return true;
            }
        }

        return false;
    }

    public function dataRedirect($id, $type, $success)
    {
        $button = '';

        $list = Transaction::where('transaction_receipt_number', $id)->first();
        if (empty($list)) {
            return response()->json(['status' => 'fail', 'messages' => ['Transaction not found']]);
        }

        $dataEncode = [
            'id_transaction'   => $list['id_transaction'],
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

        // if ($list['transaction_payment_status'] == 'Terbayar') {
        //     $title = 'Terbayar';
        // }

        // if ($list['transaction_payment_status'] == 'Sukses') {
        //     $title = 'Sukses';
        // }

        // if ($list['transaction_payment_status'] == 'Gagal') {
        //     $title = 'Gagal';
        // }

        $encode = json_encode($dataEncode);
        $base = base64_encode($encode);

        $send = [
            'button'                     => $button,
            'title'                      => $title,
            'payment_status'             => $list['transaction_payment_status'],
            'transaction_receipt_number' => $list['transaction_receipt_number'],
            'transaction_grandtotal'     => $list['transaction_grandtotal'],
            'type'                       => $type,
            'url'                        => env('API_URL').'api/transaction/web/view/detail?data='.$base
        ];

        return $send;
    }

    public function outletNotif($id_trx)
    {
        $trx = Transaction::where('id_transaction', $id_trx)->first();
        if ($trx['trasaction_type'] == 'Pickup Order') {
            $detail = TransactionPickup::where('id_transaction', $id_trx)->first();
        } else {
            $detail = TransactionShipment::where('id_transaction', $id_trx)->first();
        }

        $dataProduct = TransactionProduct::where('id_transaction', $id_trx)->with('product')->get();

        $count = count($dataProduct);
        $stringBody = "";
        $totalSemua = 0;

        foreach ($dataProduct as $key => $value) {
            $totalSemua += $value['transaction_product_qty'];
            $stringBody .= $value['product']['product_name']." - ".$value['transaction_product_qty']." pcs \r\n";
        }

        // return $stringBody;

        $outletToken = OutletToken::where('id_outlet', $trx['id_outlet'])->get();

        if (isset($detail['pickup_type'])) {
            if ($detail['pickup_type'] == 'at arrival') {
                $type = 'Saat Kedatangan';
            }

            if ($detail['pickup_type'] == 'right now') {
                $type = 'Saat Ini';
            }

            if ($detail['pickup_type'] == 'set time') {
                $type = 'Pickup';
            }
        } else {
            $type = 'Delivery';
        }

        $user = User::where('id', $trx['id_user'])->first();

        if (!empty($outletToken)) {
            $dataArraySend = [];

            foreach ($outletToken as $key => $value) {
                $dataOutletSend = [
                    'to'    => $value['token'],
                    'title' => $type.' - Rp. '.number_format($trx['transaction_grandtotal'], 0, ',', '.').' - '.$totalSemua.' pcs - '.$detail['order_id'].' - '.$user['name'].'',
                    'body'  => $stringBody,
                    'data'  => ['order_id' => $detail['order_id']]
                ];

                array_push($dataArraySend, $dataOutletSend);

            }

            $curl = $this->sendStatus('https://exp.host/--/api/v2/push/send', 'POST', $dataArraySend);
            if (!$curl) {
                DB::rollBack();
                return response()->json([
                    'status'    => 'fail',
                    'messages'  => ['Transaction failed']
                ]);
            }
        }

        return true;
    }

    public function sendStatus($url, $method, $data=null) {
        $client = new Client;

        $content = array(
            'headers' => [
                'host'            => 'exp.host',
                'accept'          => 'application/json',
                'accept-encoding' => 'gzip, deflate',
                'content-type'    => 'application/json'
            ],
            'json' => (array) $data
        );

        try {
            $response =  $client->request($method, $url, $content);
            return json_decode($response->getBody(), true);
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
            try{

                if($e->getResponse()){
                    $response = $e->getResponse()->getBody()->getContents();

                    $error = json_decode($response, true);

                    if(!$error) {
                        return $e->getResponse()->getBody();
                    } else {
                        return $error;
                    }
                } else return ['status' => 'fail', 'messages' => [0 => 'Check your internet connection.']];

            } catch(Exception $e) {
                return ['status' => 'fail', 'messages' => [0 => 'Check your internet connection.']];
            }
        }
    }

     public function getrandomstring($length = 120) {

       global $template;
       settype($template, "string");

       $template = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

       settype($length, "integer");
       settype($rndstring, "string");
       settype($a, "integer");
       settype($b, "integer");

       for ($a = 0; $a <= $length; $a++) {
               $b = rand(0, strlen($template) - 1);
               $rndstring .= $template[$b];
       }

       return $rndstring;
    }

     public function getrandomnumber($length) {

       global $template;
       settype($template, "string");

       $template = "0987654321";

       settype($length, "integer");
       settype($rndstring, "string");
       settype($a, "integer");
       settype($b, "integer");

       for ($a = 0; $a <= $length; $a++) {
               $b = rand(0, strlen($template) - 1);
               $rndstring .= $template[$b];
       }

       return $rndstring;
    }

    public function checkPromoGetPoint($promo_source)
    {
    	if (empty($promo_source)) {
    		return 1;
    	}
    	if ($promo_source != 'promo_code' && $promo_source != 'voucher_online' && $promo_source != 'voucher_offline') {
    		return 0;
    	}

    	$config = app($this->promo)->promoGetCashbackRule();
    	$getData = Configs::whereIn('config_name',['promo code get point','voucher offline get point','voucher online get point'])->get()->toArray();

    	foreach ($getData as $key => $value) {
    		$config[$value['config_name']] = $value['is_active'];
    	}

    	if ($promo_source == 'promo_code') {
    		if ($config['promo code get point'] == 1) {
    			return 1;
    		}else{
    			return 0;
    		}
    	}

    	if ($promo_source == 'voucher_online') {
    		if ($config['voucher online get point'] == 1) {
    			return 1;
    		}else{
    			return 0;
    		}
    	}

    	if ($promo_source == 'voucher_offline') {
    		if ($config['voucher offline get point'] == 1) {
    			return 1;
    		}else{
    			return 0;
    		}
    	}

    	return 0;
    }
    public function cancelTransaction(Request $request)
    {
        $id_transaction = $request->id;
        $trx = Transaction::where('id_transaction', $id_transaction)->first();
        if(!$trx || $trx->transaction_payment_status != 'Pending'){
            return MyHelper::checkGet([],'Transaction cannot be canceled');
        }
        $errors = '';

        $cancel = \Modules\IPay88\Lib\IPay88::create()->cancel('trx',$trx,$errors, $request->last_url);

        if($cancel){
            return ['status'=>'success', 'messages' => ['Your Debit/Credit Card transaction has failed. Please try again.']];
        }
        return [
            'status'=>'fail',
            'messages' => $errors?:['Something went wrong']
        ];
    }

    public function availablePayment(Request $request)
    {
        $availablePayment = config('payment_method');

        $setting  = json_decode(MyHelper::setting('active_payment_methods', 'value_text', '[]'), true) ?? [];
        $payments = [];

        foreach ($setting as $value) {
            $payment = $availablePayment[$value['code'] ?? ''] ?? false;
            if (!$payment || !($payment['status'] ?? false) || (!$request->show_all && !($value['status'] ?? false))) {
                unset($availablePayment[$value['code']]);
                continue;
            }
            $payments[] = [
                'code'            => $value['code'],
                'payment_gateway' => $payment['payment_gateway'],
                'payment_method'  => $payment['payment_method'],
                'logo'            => $payment['logo'],
                'text'            => $payment['text'],
                'status'          => (int) $value['status'] ?? 0
            ];
            unset($availablePayment[$value['code']]);
        }
        if ($request->show_all) {
            foreach ($availablePayment as $code => $payment) {
                if (!$payment['status']) {
                    continue;
                }
                $payments[] = [
                    'code'            => $code,
                    'payment_gateway' => $payment['payment_gateway'],
                    'payment_method'  => $payment['payment_method'],
                    'logo'            => $payment['logo'],
                    'text'            => $payment['text'],
                    'status'          => 0
                ];
            }
        }
        return MyHelper::checkGet($payments);
    }
    /**
     * update available payment
     * @param
     * {
     *     payments: [
     *         {'code': 'xxx', status: 1}
     *     ]
     * }
     * @return [type]           [description]
     */
    public function availablePaymentUpdate(Request $request)
    {
        $availablePayment = config('payment_method');
        foreach ($request->payments as $key => $value) {
            $payment = $availablePayment[$value['code'] ?? ''] ?? false;
            if (!$payment || !($payment['status'] ?? false)) {
                continue;
            }
            $payments[] = [
                'code'     => $value['code'],
                'status'   => $value['status'] ?? 0,
                'position' => $key + 1,
            ];
        }
        $update = Setting::updateOrCreate(['key' => 'active_payment_methods'], ['value_text' => json_encode($payments)]);
        return MyHelper::checkUpdate($update);
    }

    public function availableShipment(Request $request)
    {
        $origin = [
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ];

        $outlet = Outlet::find($request->id_outlet);
        if (!$outlet) {
            return [
                'status' => 'fail',
                'messages' => ['Outlet not found']
            ];
        }

        $destination = [
            'latitude' => $outlet->outlet_latitude,
            'longitude' => $request->outlet_longitude,
        ];

        $availableShipment = MyHelper::getDeliveries($origin, $destination, ['show_inactive' => $request->show_all]);

        return MyHelper::checkGet($availableShipment);
    }

    public function availableShipmentUpdate(Request $request)
    {
        $availabledelivery = config('delivery_method');
        $deliveries = [];
        foreach ($request->deliveries as $key => $value) {
            $delivery = $availabledelivery[$value['code'] ?? ''] ?? false;
            if (!$delivery || !($delivery['status'] ?? false)) {
                continue;
            }
            $deliveries[] = [
                'code'     => $value['code'],
                'status'   => $value['status'] ?? 0,
                'position' => $key + 1,
            ];
        }
        $update = Setting::updateOrCreate(['key' => 'active_delivery_methods'], ['value_text' => json_encode($deliveries)]);
        return MyHelper::checkUpdate($update);
    }

    public function mergeProducts($items)
    {
        $new_items = [];
        $item_qtys = [];
        $id_custom = [];

        // create unique array
        foreach ($items as $item) {
            $new_item = [
                'bonus' => isset($item['bonus'])?$item['bonus']:'0',
                'id_brand' => $item['id_brand'],
                'id_product_group' => $item['id_product_group'],
                'note' => $item['note'],
                'variants' => $item['variants'],
                'modifiers' => array_map(function($i){
                        return [
                            'id_product_modifier' => $i['id_product_modifier'],
                            'qty' => $i['qty']
                        ];
                    },$item['modifiers']??[]),
            ];
            usort($new_item['modifiers'],function($a, $b) { return $a['id_product_modifier'] <=> $b['id_product_modifier']; });
            $pos = array_search($new_item, $new_items);
            if($pos === false) {
                $new_items[] = $new_item;
                $item_qtys[] = $item['qty'];
                $id_custom[] = $item['id_custom']??0;
            } else {
                $item_qtys[$pos] += $item['qty'];
            }
        }
        // update qty
        foreach ($new_items as $key => &$value) {
            $value['qty'] = $item_qtys[$key];
            $value['id_custom'] = $id_custom[$key];
        }

        return $new_items;
    }

    public function bookDelivery(Request $request)
    {
        $post = $request->all();
        $trx = TransactionPickup::where('id_transaction', $request->id_transaction)->first();

        if (!$trx) {
            return [
                'status' => 'fail',
                'messages' => ['Transaction not found']
            ];
        }

        switch ($trx->pickup_by) {
            case 'GO-SEND':
                $trx_pickup_go_send = TransactionPickupGoSend::where('id_transaction_pickup', $trx->id_transaction_pickup)->first();
                if (!$trx_pickup_go_send) {
                    return [
                        'status' => 'fail',
                        'messages' => 'Pickup Go Send data Not found'
                    ];
                }
                $book = $trx_pickup_go_send->book(false, $errors);
                if (!$book) {
                    return [
                        'status' => 'fail',
                        'messages' => $errors ?: ['Something went wrong']
                    ];
                }

                return [
                    'status' => 'success',
                ];

                break;

            default:
                return [
                    'status' => 'fail',
                    'messages' => ['Transaction pickup by '.$trx->pickup_by]
                ];
        }
    }
}

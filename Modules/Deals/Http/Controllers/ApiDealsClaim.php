<?php

namespace Modules\Deals\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use App\Lib\MyHelper;

use App\Http\Models\Deal;
use App\Http\Models\DealsOutlet;
use App\Http\Models\DealsPaymentManual;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsVoucher;
use App\Http\Models\LogPoint;
use App\Http\Models\User;
use App\Http\Models\Setting;
use Modules\Deals\Entities\DealsUserLimit;
use Modules\Deals\Entities\SecondDealsTotal;

use Modules\Deals\Http\Controllers\ApiDealsVoucher;

use Modules\Deals\Http\Requests\Deals\Voucher;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

use DB;

class ApiDealsClaim extends Controller
{
    function __construct() {
        date_default_timezone_set('Asia/Jakarta');

        $this->deals   = "Modules\Deals\Http\Controllers\ApiDeals";
        $this->voucher = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->setting = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        if(\Module::collections()->has('Autocrm')) {
            $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        }
    }

    /* CLAIM DEALS */
    function claim(Request $request) {
    	try {
            $id_deals = $request->id_deals ?? $request->json('id_deals');
	        $dataDeals = $this->chekDealsData($id_deals);
            if(isset($request->id)){
                $user = User::where('id',$request->id)->first();
            }else{
                $user = $request->user();
            }
	        $id_user = $user->id;

	        if(isset($user->email_verified) && $user->email_verified != '1'){
	            return response()->json([
	                'status'    => 'fail',
	                'message_verfiy_email'=> 'Sorry your email has not yet been verified. Please verify your email.',
	                'messages'  => ['Sorry your email has not yet been verified. Please verify your email.']
	            ]);
	        }

	        if (empty($dataDeals)) {
	            return response()->json([
	                'status'   => 'fail',
	                'messages' => ['Data deals not found']
	            ]);
	        }
	        else {
	            // CEK VALID DATE
	            if ($this->checkValidDate($dataDeals)) {
	                // if (!empty($dataDeals->deals_voucher_price_cash) || $dataDeals->deals_promo_id_type == "nominal") {
	                if (!empty($dataDeals->deals_voucher_price_cash)) {
	                    return response()->json([
	                        'status' => 'fail',
	                        'messages' => ['You have to pay deals.']
	                    ]);
	                }
	                else {
	                    if ($this->checkDealsPoint($dataDeals, $user->id)) {
	                        // CEK USER ALREADY CLAIMED
	                        if ($this->checkUserClaimed($user, $dataDeals->id_deals)) {
                                
	            				DB::beginTransaction();
		                        if ($dataDeals->deals_type == "Subscription") {
		                            $id_deals = $dataDeals->id_deals;
                                    
		                            // count claimed deals by id_deals_subscription (how many times deals are claimed)
		                            $dealsVoucherSubs = DealsVoucher::where('id_deals', $id_deals)->count();
		                            $voucherClaimed = 0;
		                            if ($dealsVoucherSubs > 0) {
		                                $voucherClaimed = $dealsVoucherSubs / $dataDeals->total_voucher_subscription;
		                                if(is_float($voucherClaimed)){ // if miss calculate use deals_total_claimed
		                                    $voucherClaimed = $dataDeals->deals_total_claimed;
		                                }
		                            }

		                            // check available voucher
		                            if ($dataDeals->deals_total_voucher > $voucherClaimed || $dataDeals->deals_voucher_type == "Unlimited") {
		                                $deals_subs = $dataDeals->deals_subscriptions()->get();

		                                // create deals voucher and deals user x times
		                                $user_voucher_array = [];
		                                $apiDealsVoucher = new ApiDealsVoucher();

		                                foreach ($deals_subs as $key => $deals_sub) {
		                                    // deals subscription may have > 1 voucher
		                                    for ($i=1; $i <= $deals_sub->total_voucher; $i++) {
		                                        // generate voucher code
		                                        do {
		                                            $code = $apiDealsVoucher->generateCode($dataDeals->id_deals);
		                                            $voucherCode = DealsVoucher::where('id_deals', $id_deals)->where('voucher_code', $code)->first();
		                                        } while (!empty($voucherCode));

		                                        $deals_voucher = DealsVoucher::create([
		                                            'id_deals'             => $id_deals,
		                                            'id_deals_subscription'=> $deals_sub->id_deals_subscription,
		                                            'voucher_code'         => strtoupper($code),
		                                            'deals_voucher_status' => 'Sent',
		                                        ]);
		                                        if (!$deals_voucher) {
		                                            DB::rollBack();
		                                            return response()->json([
		                                                'status'   => 'fail',
		                                                'messages' => ['Failed to save data.']
		                                            ]);
		                                        }

		                                        // create user voucher
		                                        // give price to user voucher only if first voucher
		                                        if ($key==0 && $i==1) {
		                                            $user_voucher = $this->createVoucherUser($id_user, $deals_voucher->id_deals_voucher, $dataDeals, $deals_sub);
		                                            $voucher = $user_voucher;
		                                        }
		                                        else{
		                                            // price or point = null
		                                            $user_voucher = $this->createVoucherUser($id_user, $deals_voucher->id_deals_voucher, $dataDeals, $deals_sub, 0);
		                                        }
		                                        if (!$user_voucher) {
		                                            DB::rollBack();
		                                            return response()->json([
		                                                'status'   => 'fail',
		                                                'messages' => ['Failed to save data.']
		                                            ]);
		                                        }
		                                        // keep user voucher in order to return in response
		                                        array_push($user_voucher_array, $user_voucher);

		                                    }   // end of for

		                                }   // end of foreach

		                                // update deals total claim
		                                $updateDeals = $this->updateDeals($dataDeals);
		                            }
		                            else {
		                                DB::rollBack();
		                                return response()->json([
		                                    'status'   => 'fail',
		                                    'messages' => ['This voucher is no longer available.']
		                                ]);
		                            }
		                        }
		                        else{
		                            // CHECK TYPE VOUCHER
		                            // IF LIST VOUCHER, GET 1 FROM DEALS VOUCHER
		                            if ($dataDeals->deals_voucher_type == "List Vouchers") {
		                                $voucher = $this->getVoucherFromTable($user, $dataDeals);

		                                if (!$voucher) {
		                                    DB::rollBack();
		                                    return response()->json([
		                                        'status'   => 'fail',
		                                        'messages' => ['This voucher is no longer available.']
		                                    ]);
		                                }
		                            }
		                            // GENERATE VOUCHER CODE & ASSIGN
		                            else {
		                                $voucher = $this->getVoucherGenerate($user, $dataDeals);
                                        
		                                if (!$voucher) {
		                                    DB::rollBack();
		                                    return response()->json([
		                                        'status'   => 'fail',
		                                        'messages' => ['This voucher is no longer available.']
		                                    ]);
		                                }
		                            }
		                        }

		                        // UPDATE POINT
		                        if (!$this->updatePoint($voucher)) {
		                            DB::rollBack();
		                            return response()->json([
		                                'status'   => 'fail',
		                                'messages' => ['Proses pengambilan voucher gagal, silakan mencoba kembali']
		                            ]);
		                        }

		                        // dd($user_voucher_array);
		                        DB::commit();

		                        // assign deals subscription vouchers to response
		                        if ($dataDeals->deals_type == "Subscription") {
		                            $voucher = $user_voucher_array;
		                        }

		                        // if(isset($voucher['deals_voucher']['id_deals'])){
		                        //     $voucher['deals'] = Deal::find($voucher['deals_voucher']['id_deals']);
		                        // }
                                if(isset($request->id)){
                                    if(\Module::collections()->has('Autocrm')) {
                                        $phone=$user->phone;
                                        $autocrm = app($this->autocrm)->SendAutoCRM('Claim Second Order Voucher', $phone,
                                            [
                                                'deals_title'      => $dataDeals->deals_title,
                                                'deals_content_push'    => $dataDeals->autoresponse_notification,
                                                'deals_content_inbox'    => $dataDeals->autoresponse_inbox,
                                            ]
                                        );
                                    }
                                }else{
                                    if(\Module::collections()->has('Autocrm')) {
                                        $phone=$user->phone;
                                        $autocrm = app($this->autocrm)->SendAutoCRM('Claim Free Deals Success', $phone,
                                            [
                                                'claimed_at'       => $voucher['claimed_at'],
                                                'deals_title'      => $dataDeals->deals_title,
                                                'id_deals_user'    => $voucher['id_deals_user'],
                                                'id_deals'         => $dataDeals->id_deals,
                                                'id_brand'         => $dataDeals->id_brand
                                            ]
                                        );
                                    }
                                }
		                        $return=[
		                            'id_deals_user'=>$voucher['id_deals_user'],
		                            'id_deals_voucher'=>$voucher['id_deals_voucher'],
		                            'paid_status'=>$voucher['paid_status'],
		                            'webview_later'=>env('API_URL').'api/webview/mydeals/'.$voucher['id_deals_user']
		                        ];
		                        return response()->json(MyHelper::checkCreate($return));
	                        }
	                        else {
	                            DB::rollBack();
	                            return response()->json([
	                                'status'   => 'fail',
	                                'messages' => ['You have participated.']
	                            ]);
	                        }
	                    }
	                    else {
	                        DB::rollBack();
	                        return response()->json([
	                            'status'   => 'fail',
	                            'messages' => ['Your point is not enough.']
	                        ]);
	                    }
	                }
	            }
	            else {
	                DB::rollBack();
	                return response()->json([
	                    'status' => 'fail',
	                    'messages' => ['Date valid '.date('d F Y', strtotime($dataDeals->deals_start)).' until '.date('d F Y', strtotime($dataDeals->deals_end))]
	                ]);
	            }

	        }
    		
    	} catch (\Exception $e) {
    		\Log::info($e);
    		return response()->json([
	            'status'   => 'fail',
	            'messages' => ['Deals claim failed.']
	        ]);
    	}
    }

    /* CHEK USER ALREADY CLAIMED */
    function checkUserClaimed($user, $id_deals, $deleteIfFalse = false) {
        $claimed = DealsUser::join('deals_vouchers', 'deals_vouchers.id_deals_voucher', '=', 'deals_users.id_deals_voucher')->where('id_user', $user->id)->where('deals_vouchers.id_deals', $id_deals)->where('paid_status', '<>', 'Cancelled')->count();

        $checkLimit = Deal::where('id_deals', $id_deals)->first();
        if (empty($checkLimit)) {
            return false;
        }

        if ($checkLimit['user_limit'] != 0) {
            if (!empty($claimed)) {
                if ($claimed >= $checkLimit['user_limit']) {
                    return false;
                }
            }
        } elseif ($deleteIfFalse) {
            $delete = DealsUserLimit::where([
                'id_user'   => $user->id,
                'id_deals'  => $id_deals
            ])->delete();
        }

        return true;
    }

    /* CHECK USER HAVE ENOUGH POINT */
    function checkDealsPoint($deals, $user) {
        if (!empty($deals->deals_voucher_price_point)) {
            if ($deals->deals_voucher_price_point > $this->getPoint($user)) {
                return false;
            }
        }

        return true;
    }

    /* CHECK VALID DATE */
    function checkValidDate($deals) {
        if (empty($deals->deals_start) && empty($deals->deals_end)) {
            return true;
        }

        if (strtotime($deals->deals_start) <= strtotime(date('Y-m-d H:i:s')) && strtotime($deals->deals_end) >= strtotime(date('Y-m-d H:i:s'))) {

            return true;
        }

        return false;
    }

    /* CHEK DATA DEALS */
    function chekDealsData($id_deals) {
        $deals = Deal::where('id_deals', $id_deals)->first();

        return $deals;
    }

    /* GET VOUCHER FROM TABLE VOUCHER */
    function getVoucherFromTable($user, $dataDeals) {
        $getOne = DealsVoucher::where('id_deals', $dataDeals->id_deals)->where('deals_voucher_status', '=', 'Available')->first();

        if (!empty($getOne)) {
            // UPDATE VOUCHER
            $updateVoucher = $this->updateVoucher($getOne);

            if ($updateVoucher) {
                // UPDATE DEALS
                $updateDeals = $this->updateDeals($dataDeals);

                if ($updateDeals) {
                    // CREATE USER VOUCHER
                    $createVoucherUser = $this->createVoucherUser($user->id, $getOne->id_deals_voucher, $dataDeals);

                    return $createVoucherUser;
                }
                else {
                    return $updateDeals;
                }
            }
            else {
                return $updateVoucher;
            }
        }

        return false;
    }

    /* UPDATE DEALS */
    function updateDeals($dataDeals, $user = []) {
        if(isset($user)){
            $id_user = $user['id'];
        }else{
            $id_user = Auth::id();
        }
        
    	$total_claimed = DealsVoucher::where('deals_vouchers.id_deals', $dataDeals->id_deals)
	        			->join('deals_users', 'deals_users.id_deals_voucher','=','deals_vouchers.id_deals_voucher')
                        ->where('paid_status', '!=', 'Cancelled')
                        ->where('id_user', $id_user)
	        			->count();
	    if ($dataDeals->user_limit != 0) {
            if (!empty($total_claimed)) {
                if ($total_claimed >= $dataDeals->user_limit) {
                    return false;
                }
            	
            }

            if (($total_claimed+1) == $dataDeals->user_limit) {
                $dataUserLimit = [
                    'id_user' 	=> $id_user,
                    'id_deals'	=> $dataDeals->id_deals
                ];
                $saveDealsLimit = DealsUserLimit::create($dataUserLimit);
            }
        }

        $total_claimed_deals = DealsVoucher::where('deals_vouchers.id_deals', $dataDeals->id_deals)
	        			->join('deals_users', 'deals_users.id_deals_voucher','=','deals_vouchers.id_deals_voucher')
                        ->where('paid_status', '!=', 'Cancelled')
	        			->count();
                        
        $update = Deal::where('id_deals', $dataDeals->id_deals)->update(['deals_total_claimed' => $total_claimed_deals + 1]);

        return $update;

    }

    /* UPDATE VOUCHER */
    function updateVoucher($getOne) {
        $save = app($this->voucher)->update($getOne->id_deals_voucher, ['deals_voucher_status' => 'Sent']);
        return $save;
    }

    /* CREATE USER */
    function createVoucherUser($id, $voucher, $dataDeals, $deals_subscription=null, $price=null) {
        $deals_voucher_price_point = $dataDeals->deals_voucher_price_point;
        $deals_voucher_price_cash = $dataDeals->deals_voucher_price_cash;

        // for deals subscription, only 1 voucher that have price
        if ($price===0 && $deals_subscription!=null) {
            $deals_voucher_price_point = null;
            $deals_voucher_price_cash = null;
        }
        $data = [
            'id_user'             => $id,
            'id_deals_voucher'    => $voucher,
            'claimed_at'          => date('Y-m-d H:i:s'),
            'voucher_price_point' => $deals_voucher_price_point,
            'voucher_price_cash'  => $deals_voucher_price_cash,
            'deals_receipt_number' => 'TRXD-'.date('ymd').MyHelper::createrandom(6,'Besar')
        ];
        // if ($dataDeals->deals_promo_id_type == "nominal") {
        //     $data['voucher_price_cash'] = $dataDeals->deals_promo_id;
        // }

        // EXPIRED DATE
        if ($dataDeals->deals_type == "Subscription") {
            if ($deals_subscription == null) {
                return false;
            }
            // add id deals
            $data['id_deals'] = $dataDeals->id_deals;

            // get active and expired date of deals subscription
            $data['voucher_active_at'] = date('Y-m-d H:i:s', strtotime("+".$deals_subscription->voucher_start." days"));
            $data['voucher_expired_at'] = date('Y-m-d H:i:s', strtotime("+".$deals_subscription->voucher_end." days"));
        }
        else{
            if (!empty($dataDeals->deals_voucher_duration)) {
                if($dataDeals->deals_voucher_start>date('Y-m-d H:i:s')){
                    $data['voucher_expired_at'] = date('Y-m-d H:i:s', strtotime($dataDeals->deals_voucher_start." +".$dataDeals->deals_voucher_duration." days"));
                }else{
                    $data['voucher_expired_at'] = date('Y-m-d H:i:s', strtotime("+".$dataDeals->deals_voucher_duration." days"));
                }
            }
            else {
                $data['voucher_expired_at'] = $dataDeals->deals_voucher_expired;
            }

            if (!empty($dataDeals->deals_voucher_start)) {
            	$data['voucher_active_at'] = $dataDeals->deals_voucher_start;
            }
        }

        // CHECK PAYMENT = FREE / NOT
        if (empty($dataDeals->deals_voucher_price_cash) && empty($dataDeals->deals_voucher_price_point)) {
            $data['paid_status'] = "Free";
        }
        // else {
        //     $data['paid_status'] = "Completed";
        // }

        if (!empty($dataDeals->deals_voucher_price_cash) && empty(!$dataDeals->deals_voucher_price_point)) {
            $data['paid_status'] = "Pending";
        }


        // CHECK PAYMENT WITH POINT
        // SUM POINT
        // if ($dataDeals->deals_voucher_price_point <= $this->getPoint($id)) {
        //     $data['paid_status'] = "success";
        // }
        // else {
        //     $data['paid_status'] = "Pending";
        // }

        $save = app($this->voucher)->createVoucherUser($data);

        return $save;
    }

    /*=============================================================================*/
    //
    //
    /*=============================================================================*/

    /* GET VOUCHER GENERATE */
    function getVoucherGenerate($user, $dataDeals) {
    	$available = 0;
    	if ($dataDeals->deals_voucher_type != "Unlimited") {
	        $available = DealsVoucher::where('deals_vouchers.id_deals', $dataDeals->id_deals)
	        			->leftJoin('deals_users', 'deals_users.id_deals_voucher','=','deals_vouchers.id_deals_voucher')
	        			->where('paid_status', '!=', 'Cancelled')
	        			->count();
    	}
        if ($dataDeals->deals_voucher_type == "Unlimited" || $dataDeals->deals_total_voucher > $available || ($dataDeals->deals_type =='SecondDeals' && $dataDeals->deals_total_voucher == 0)) {
            // GENERATE VOUCHER
            $voucher = $this->generateVoucher($dataDeals->id_deals);

            // UPDATE VOUCHER
            $updateVoucher = $this->updateVoucher($voucher);

            if ($updateVoucher) {
                // UPDATE DEALS
                $updateDeals = $this->updateDeals($dataDeals, $user);

                if ($updateDeals) {
                    // CREATE USER VOUCHER
                    $createVoucherUser = $this->createVoucherUser($user->id, $voucher->id_deals_voucher, $dataDeals);

                    return $createVoucherUser;
                }
                else {
                    return $updateDeals;
                }
            }
            else {
                return $updateVoucher;
            }
        }

        return false;
    }

    /* GET POINT */
    function getPoint($user) {
        // if (Schema::hasTable('log_points')) {

        //     $point = DB::table('log_points')->where('id_user', $user)->sum('point');

        //     return $point;
        // }

        //point is balance
        if (Schema::hasTable('log_balances')) {

            $point = DB::table('log_balances')->where('id_user', $user)->sum('balance');

            return $point;
        }

        return 0;
    }

    /* GENERATE VOUCHER */
    function generateVoucher($id_deals) {
        $generate = app($this->voucher)->generateVoucher($id_deals, 1);

        if ($generate) {
            // GET VOUCHER
            $voucher = DealsVoucher::where('id_deals', $id_deals)->where('deals_voucher_status', '=', 'Available')->first();

            return $voucher;
        }

        return $generate;
    }

    function updatePoint($voucher)
    {
        return $user = User::with('memberships')->where('id', $voucher->id_user)->first();

        $user_member = $user->toArray();
        $level = null;
        $point_percentage = 0;
        if (!empty($user_member['memberships'][0]['membership_name'])) {
            $level = $user_member['memberships'][0]['membership_name'];
        }
        if (isset($user_member['memberships'][0]['benefit_point_multiplier'])) {
            $point_percentage = $user_member['memberships'][0]['benefit_point_multiplier'];
        }

        // $setting = app($this->setting)->setting('point_conversion_value');
        $setting = Setting::where('key', 'point_conversion_value')->pluck('value')->first();

        $dataCreate        = [
            'id_user'          => $voucher->id_user,
            'id_reference'     => $voucher->id_deals_user,
            'source'           => "Deals User",
            'point'            => -$voucher->voucher_price_point,
            'voucher_price'    => $voucher->voucher_price_point,
            'point_conversion' => $setting,
            'membership_level'            => $level,
            'membership_point_percentage' => $point_percentage
        ];
        $save = LogPoint::create($dataCreate);

        // update user point
        $new_user_point = LogPoint::where('id_user', $user->id)->sum('point');
        $user->update(['points' => $new_user_point]);

        return $save;
    }

    public function getSecondVoucher($data){
        $id_user = $data['id_user'] ?? $data['id'];
        $date = date('Y-m-d H:i:s');

        $setting = Setting::where('key', 'second_deals_setting')->first();
        if($setting['value'] == 1){
            $getDeals = SecondDealsTotal::join('deals', 'deals.id_deals', 'second_deals_totals.id_deals')
            ->where('deals.step_complete', 1)
            ->whereDate('deals.deals_start', '<=', $date)
            ->whereDate('deals.deals_end', '>=', $date)
            ->select('deals.*','second_deals_totals.deals_total')
            ->get()->toArray();
            foreach($getDeals ?? [] as $deal){
                for($i = 0; $i < $deal['deals_total']; $i++){
                    $send = ['id_deals' => $deal['id_deals'],'id'=>$id_user];
                    $claim = $this->claimSecondDeals(New Request($send));
                }
            }
        }
        return true;
    }

    public function checkMutlipleCategory($products = []){
        
        $check = false;
        $id = null;
        foreach($products ?? [] as $product){
            if(!isset($id) && empty($id)){
                $id = $product['id_product_category'];
            }else{
                if($id != $product['id_product_category']){
                    $check = true;
                }
                $id = $product['id_product_category'];
            }
        }
        return true;
      
    }

    function claimSecondDeals(Request $request){
        
        $id_deals = $request->id_deals ?? $request->json('id_deals');
        $dataDeals = $this->chekDealsData($id_deals);
        $user = User::where('id',$request->id)->first();
        
        $id_user = $user->id;

        if(isset($user->email_verified) && $user->email_verified == '1'){
            if (!empty($dataDeals)) {
                if ($this->checkValidDate($dataDeals)) {
                    
                    if (empty($dataDeals->deals_voucher_price_cash)) {

                        if ($this->checkDealsPoint($dataDeals, $user->id)) {
                            
	                        if ($this->checkUserClaimed($user, $dataDeals->id_deals)) {
                                
		                        if ($dataDeals->deals_type != "Subscription") {
		                            $voucher = $this->getVoucherGenerate($user, $dataDeals);
                                    $this->updatePoint($voucher);

                                    if(\Module::collections()->has('Autocrm')) {
                                        $phone=$user->phone;
                                        $autocrm = app($this->autocrm)->SendAutoCRM('Claim Second Order Voucher', $phone,
                                            [
                                                'deals_title'      => $dataDeals->deals_title,
                                                'deals_content_push'    => $dataDeals->autoresponse_notification,
                                                'deals_content_inbox'    => $dataDeals->autoresponse_inbox,
                                            ]
                                        );
                                    }
                                    
                                    return true;
                                }
                            }
                        }
                    }
                }
            } 
        }
    }

}

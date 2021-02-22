<?php

namespace Modules\PromoCampaign\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\PromoCampaign\Entities\PromoCampaignOutlet;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\PromoCampaign\Entities\PromoCampaignProductDiscount;
use Modules\PromoCampaign\Entities\PromoCampaignProductDiscountRule;
use Modules\PromoCampaign\Entities\PromoCampaignTierDiscountProduct;
use Modules\PromoCampaign\Entities\PromoCampaignTierDiscountRule;
use Modules\PromoCampaign\Entities\PromoCampaignBuyxgetyProductRequirement;
use Modules\PromoCampaign\Entities\PromoCampaignBuyxgetyRule;
use Modules\PromoCampaign\Entities\PromoCampaignHaveTag;
use Modules\PromoCampaign\Entities\PromoCampaignTag;
use Modules\PromoCampaign\Entities\PromoCampaignReport;
use Modules\PromoCampaign\Entities\UserReferralCode;
use Modules\PromoCampaign\Entities\UserPromo;;

use Modules\Deals\Entities\DealsProductDiscount;
use Modules\Deals\Entities\DealsProductDiscountRule;
use Modules\Deals\Entities\DealsTierDiscountProduct;
use Modules\Deals\Entities\DealsTierDiscountRule;
use Modules\Deals\Entities\DealsBuyxgetyProductRequirement;
use Modules\Deals\Entities\DealsBuyxgetyRule;

use Modules\ProductVariant\Entities\ProductGroup;

use App\Http\Models\User;
use App\Http\Models\Configs;
use App\Http\Models\Campaign;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\Setting;
use App\Http\Models\Voucher;
use App\Http\Models\Treatment;
use App\Http\Models\Deal;
use App\Http\Models\DealsUser;

use Modules\PromoCampaign\Http\Requests\Step1PromoCampaignRequest;
use Modules\PromoCampaign\Http\Requests\Step2PromoCampaignRequest;
use Modules\PromoCampaign\Http\Requests\DeletePromoCampaignRequest;
use Modules\PromoCampaign\Http\Requests\ValidateCode;
use Modules\PromoCampaign\Http\Requests\UpdateCashBackRule;
use Modules\PromoCampaign\Http\Requests\CheckUsed;

use Modules\PromoCampaign\Lib\PromoCampaignTools;
use App\Lib\MyHelper;
use App\Jobs\GeneratePromoCode;
use DB;
use Hash;
use Modules\SettingFraud\Entities\DailyCheckPromoCode;
use Modules\SettingFraud\Entities\LogCheckPromoCode;

class ApiPromo extends Controller
{

	function __construct() {
        date_default_timezone_set('Asia/Jakarta');

        $this->online_transaction   = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->voucher   = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->fraud   = "Modules\SettingFraud\Http\Controllers\ApiFraud";
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
    }

    public function checkUsedPromo(CheckUsed $request)
    {
    	$user = auth()->user();
    	$datenow = date("Y-m-d H:i:s");
    	$remove = 0;
    	$remove_delivery = 0;
    	$promo = null;
    	$promo_delivery = null;
		DB::beginTransaction();

		// discount
    	$user_promo = UserPromo::where('id_user','=',$user->id)->where('discount_type', 'discount')->first();
    	$user_promo_delivery = UserPromo::where('id_user','=',$user->id)->where('discount_type', 'delivery')->first();

    	if (!$user_promo && !$user_promo_delivery) {
    		return response()->json(['status' => 'fail']);
    	}

    	if ($user_promo) {
	    	if ($user_promo->promo_type == 'deals')
	    	{
	    		$promo = app($this->promo_campaign)->checkVoucher($user_promo->id_reference, null, 1);

	    		if ($promo) {
	    			if ($promo->used_at) {
	    				$remove = 1;
	    			}elseif($promo->voucher_expired_at < $datenow){
	    				$remove = 1;
	    			}
	    		}
	    	}
	    	else
	    	{
	    		$promo = app($this->promo_campaign)->checkPromoCode(null, null, 1, $user_promo->id_reference);
				if ($promo)
				{
					if ($promo->date_end < $datenow) {
						$remove = 1;
					}else{
						$pct = new PromoCampaignTools;
						$validate_user=$pct->validateUser($promo->id_promo_campaign, $user->id, $user->phone, null, $request->device_id, $error,$promo->id_promo_campaign_promo_code);
						if (!$validate_user) {
							$remove = 1;
						}
					}
				}
	    	}

	    	if ( $promo['dealVoucher']['deals'] ?? $promo['promo_campaign'] ?? false ) {

		    	$getProduct = app($this->promo_campaign)->getProduct($user_promo->promo_type,$promo['dealVoucher']['deals']??$promo['promo_campaign']);
		    	$promo = $promo->toArray();
		    	$desc = app($this->promo_campaign)->getPromoDescription($user_promo->promo_type, $promo['deal_voucher']['deals']??$promo['promo_campaign'], $getProduct['product']??'');
	    	}
    	}

    	if ($user_promo_delivery) {
    		if ($user_promo_delivery->promo_type == 'deals')
	    	{
	    		$promo_delivery = app($this->promo_campaign)->checkVoucher($user_promo_delivery->id_reference, null, 1);

	    		if ($promo_delivery) {
	    			if ($promo_delivery->used_at) {
	    				$remove_delivery = 1;
	    			}elseif($promo_delivery->voucher_expired_at < $datenow){
	    				$remove_delivery = 1;
	    			}
	    		}
	    	}
	    	else
	    	{
	    		$promo_delivery = app($this->promo_campaign)->checkPromoCode(null, null, 1, $user_promo_delivery->id_reference);
				if ($promo_delivery)
				{
					if ($promo_delivery->date_end < $datenow) {
						$remove_delivery = 1;
					}else{
						$pct = new PromoCampaignTools;
						$validate_user=$pct->validateUser($promo_delivery->id_promo_campaign, $user->id, $user->phone, null, $request->device_id, $error,$promo_delivery->id_promo_campaign_promo_code);
						if (!$validate_user) {
							$remove_delivery = 1;
						}
					}
				}
	    	}
    	}

    	// delivery
    	if ( ($user_promo && !$promo) && ($user_promo_delivery && !$promo_delivery) ) {
    		return response()->json(['status' => 'fail']);
    	}

    	$result = [
    		'title'				=> $promo['deal_voucher']['deals']['deals_title']??$promo['promo_campaign']['promo_title'] ?? null,
    		'description'		=> $desc ?? null,
    		'id_deals_user'		=> $promo['id_deals_user'] ?? '',
    		'promo_code'		=> $promo['promo_code'] ?? '',
    		'remove'			=> $remove,
    		'promo_delivery'	=> [
	    		'id_deals_user_delivery' 	=> $promo_delivery['id_deals_user'] ?? null,
	    		'promo_code_delivery'		=> $promo_delivery['promo_code'] ?? '',
	    		'remove_delivery'			=> $remove_delivery
	    	]
    	];
    	return response()->json(MyHelper::checkGet($result));

    }

    public function usePromo($source, $id_promo, $query, $status='use')
    {
    	$user = auth()->user();
		DB::beginTransaction();

		if ($source == 'deals') {
			if ($query['deals']['promo_type'] == 'Discount delivery') {
				$discount_type = 'delivery';
			}else{
				$discount_type = 'discount';
			}
		}else{
			if ($query['promo_campaign']['promo_type'] == 'Discount delivery') {
				$discount_type = 'delivery';
			}else{
				$discount_type = 'discount';
			}
		}

		$user_promo = UserPromo::where('id_user','=',$user->id)->where('discount_type', $discount_type)->first();

    	if ( ($user_promo->promo_type ?? false) == 'deals' && ($user_promo->discount_type ?? false) == $discount_type) 
    	{
			// change is used flag to 0
			$update = DealsUser::where('id_deals_user','=',$user_promo->id_reference)->update(['is_used' => 0]);
    	}

		if ($status == 'use')
		{
			if ($source == 'deals')
			{
				// change specific deals user is used to 1
				$update = DealsUser::where('id_deals_user','=',$id_promo)->update(['is_used' => 1]);
			}
			$update = UserPromo::updateOrCreate(['id_user' => $user->id, 'discount_type' => $discount_type], ['promo_type' => $source, 'id_reference' => $id_promo]);

		}
		else
		{
			$update = UserPromo::where('id_user', '=', $user->id)->delete();
		}

		if ($update) {
			DB::commit();
		}else{
			DB::rollBack();
		}

		$update = MyHelper::checkUpdate($update);
		$update['webview_url'] = "";
		$update['webview_url_v2'] = "";
		if ($source == 'deals')
		{
			$update['webview_url'] = env('API_URL') ."api/webview/voucher/". $id_promo;
			$update['webview_url_v2'] = env('API_URL') ."api/webview/voucher/v2/". $id_promo;
		}
		return $update;

    }

    public function cancelPromo(Request $request)
    {
    	$post = $request->json()->all();
    	$user = auth()->user();

    	if (!empty($post['id_deals_user']))
    	{
    		$source = 'deals';
    	}
    	else
    	{
    		$source = 'promo_campaign';
    	}

    	$user_promo = UserPromo::where('id_user','=',$user->id)->where('discount_type', $request->promo_type)->first();

    	if ( ($user_promo->promo_type ?? false) == 'deals' ) 
    	{
			// change is used flag to 0
			$update = DealsUser::where('id_deals_user','=',$user_promo->id_reference)->update(['is_used' => 0]);
    	}

    	$cancel = UserPromo::where('id_user', '=', $user->id)->where('discount_type', $request->promo_type)->delete();

    	if ($cancel) {
    		$result = MyHelper::checkDelete($cancel);
			$result['webview_url'] = "";
			$result['webview_url_v2'] = "";
			if ($source == 'deals')
			{
				$result['webview_url'] = env('API_URL') ."api/webview/voucher/". $user_promo->id_reference;
				$result['webview_url_v2'] = env('API_URL') ."api/webview/voucher/v2/". $user_promo->id_reference;
			}

			return $result;
    	}else{
    		return response()->json([
    			'status' => 'fail',
    			'messages' => 'Failed to update promo'
    		]);
    	}
    }

    public function promoGetCashbackRule()
    {
    	$getData = Configs::whereIn('config_name',['promo code get point','voucher offline get point','voucher online get point'])->get()->toArray();

    	foreach ($getData as $key => $value) {
    		$config[$value['config_name']] = $value['is_active'];
    	}

    	return $config;
    }

    public function getDataCashback(Request $request)
    {
    	$data = $this->promoGetCashbackRule();

    	return response()->json(myHelper::checkGet($data));
    }

    public function updateDataCashback(UpdateCashBackRule $request)
    {
    	$post = $request->json()->all();
    	db::beginTransaction();
    	$update = Configs::where('config_name','promo code get point')->update(['is_active' => $post['promo_code_cashback']??0]);
    	$update = Configs::where('config_name','voucher online get point')->update(['is_active' => $post['voucher_online_cashback']??0]);
    	$update = Configs::where('config_name','voucher offline get point')->update(['is_active' => $post['voucher_offline_cashback']??0]);

    	if(is_numeric($update))
    	{
    		db::commit();
    	}else{
    		db::rollBack();
    	}

    	return response()->json(myHelper::checkUpdate($update));
    }
}

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

    public function checkUsedPromo(Request $request)
    {
    	$user = auth()->user();
		DB::beginTransaction();
    	$user_promo = UserPromo::where('id_user','=',$user->id)->first();
    	if (!$user_promo) {
    		return response()->json(['status' => 'fail']);
    	}

    	if ($user_promo->promo_type == 'deals') 
    	{
    		$promo = app($this->promo_campaign)->checkVoucher(null, null, 1);
    	}
    	else
    	{
    		$promo = app($this->promo_campaign)->checkPromoCode(null, null, 1, $user_promo->id_reference);
    	}

    	$promo = $promo->toArray();

    	$getProduct = app($this->promo_campaign)->getProduct($user_promo->promo_type,$promo['deal_voucher']['deals']??$promo['promo_campaign']);
    	$desc = app($this->promo_campaign)->getPromoDescription($user_promo->promo_type, $promo['deal_voucher']['deals']??$promo['promo_campaign'], $getProduct['product']??'');

    	$result = [
    		'title'				=> $promo['deal_voucher']['deals']['deals_title']??$promo['promo_campaign']['promo_title'],
    		'description'		=> $desc,
    		'id_deals_user'		=> $promo['id_deals_user']??'',
    		'promo_code'		=> $promo['promo_code']??''
    	];
    	return response()->json(MyHelper::checkGet($result));

    }

    public function usePromo($source, $id_promo, $status='use')
    {
    	$user = auth()->user();
		DB::beginTransaction();

		// change is used flag to 0
		$update = DealsUser::where('id_user','=',$user->id)->where('is_used','=',1)->update(['is_used' => 0]);

		if ($status == 'use') 
		{
			if ($source == 'deals') 
			{
				// change specific deals user is used to 1
				$update = DealsUser::where('id_deals_user','=',$id_promo)->update(['is_used' => 1]);
			}

			$update = UserPromo::updateOrCreate(['id_user' => $user->id], ['promo_type' => $source, 'id_reference' => $id_promo]);
		}
		else
		{
			$update = UserPromo::where('id_user', '=', $user->id)->delete();
		}

		if ($update) {
			DB::commit();
		}else{
			DB::rollback();
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

    	if (!empty($post['id_deals_user'])) 
    	{
    		$source = 'deals';
    	}
    	else
    	{
    		$source = 'promo_campaign';
    	}
    	$cancel = $this->usePromo($source, $post['id_deals_user'], 'cancel');

    	if ($cancel) {
    		return response()->json($cancel);
    	}else{
    		return response()->json([
    			'status' => 'fail',
    			'messages' => 'Failed to update promo'
    		]);
    	}
    }
}
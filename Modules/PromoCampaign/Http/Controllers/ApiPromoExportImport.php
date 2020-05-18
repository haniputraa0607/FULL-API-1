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

use Modules\PromoCampaign\Http\Requests\ExportRequest;
use Modules\PromoCampaign\Http\Requests\ImportRequest;

use Modules\PromoCampaign\Lib\PromoCampaignTools;
use App\Lib\MyHelper;
use App\Jobs\GeneratePromoCode;
use DB;
use Hash;
use Modules\SettingFraud\Entities\DailyCheckPromoCode;
use Modules\SettingFraud\Entities\LogCheckPromoCode;

class ApiPromoExportImport extends Controller
{

	function __construct() {
        date_default_timezone_set('Asia/Jakarta');

        $this->online_transaction   = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->deals 	= "Modules\Deals\Http\Controllers\ApiDeals";
        $this->voucher 	= "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->fraud   	= "Modules\SettingFraud\Http\Controllers\ApiFraud";
    }

    public function exportPromoCampaign(ExportRequest $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $promo = PromoCampaign::with(
                            'promo_campaign_have_tags.promo_campaign_tag',
                            'promo_campaign_product_discount_rules',
                            'promo_campaign_product_discount.product',
                            'promo_campaign_tier_discount_rules',
                            'promo_campaign_tier_discount_product.product',
                            'promo_campaign_buyxgety_rules.product',
                            'promo_campaign_buyxgety_product_requirement.product',
                            'outlets'
                        )
                        ->where('id_promo_campaign', '=', $post['id_promo_campaign'])
                        ->first();

        if (isset($promo)) {
	        if ( ($promo['code_type']??false) == 'Single') {
	        	$promo->load('promo_campaign_promo_codes');
	        }

        	$promo = $promo->toArray();
        	
        }else{
            $promo = false;
        }

        $data['rule'] = [];
        $data['outlet'] = [];
        if ($promo) 
        {
        	if ( !empty($promo['promo_campaign_promo_codes'][0]['promo_code']) ) {
	        	$promo['promo_code'] = $promo['promo_campaign_promo_codes'][0]['promo_code'];
	        }

	        $data['outlet'] = [];
	        foreach ($promo['outlets'] as $key => $value) {
	        	$data['outlet'][] = [
	        		'outlet_code' => $value['outlet_code'],
	        		'outlet_name' => $value['outlet_name']
	        	];
	        }
	        if ($data['outlet'] == []) {
	        	unset($data['outlet']);
	        }

	        $data['tags'] = [];

	        foreach ($promo['promo_campaign_have_tags'] as $key => $value) {
	        	$data['tags'][]['tag'] = $value['promo_campaign_tag']['tag_name'];
	        }
	        if ($data['tags'] == [] ) {
    			unset($data['tags']);
    		}

	        switch ($promo['promo_type'])
	        {
	        	case 'Product discount':
	        		
	        		$promo['is_all_product'] = $promo['promo_campaign_product_discount_rules']['is_all_product'];
	        		$promo['product_discount_type'] = $promo['promo_campaign_product_discount_rules']['discount_type'];
	        		$promo['product_discount_value'] = $promo['promo_campaign_product_discount_rules']['discount_value'];
	        		$promo['product_discount_max_qty'] = $promo['promo_campaign_product_discount_rules']['max_product'];
	        		$promo['product_discount_max_discount'] = $promo['promo_campaign_product_discount_rules']['max_percent_discount'];

	        		$temp_product = [];
	        		foreach ($promo['promo_campaign_product_discount'] as $key => $value) {
	        			$temp_product[] = [
	        				'product_code' => $value['product']['product_code']??$value['product_group']['product_group_code']??'', 
	        				'product_name' => $value['product']['product_name']??$value['product_group']['product_group_name']??''
	        			];
	        		}

	        		$data['detail_rule_product_discount'] = $temp_product;
	        		if ($data['detail_rule_product_discount'] == [] ) {
	        			unset($data['detail_rule_product_discount']);
	        		}
	        		break;
	        	
	        	case 'Tier discount':

	        		$promo['tier_discount_product_code'] = $promo['promo_campaign_tier_discount_product']['product']['product_code']??$promo['promo_campaign_tier_discount_product']['product_group']['product_group_code']??'';
	        		$promo['tier_discount_product_name'] = $promo['promo_campaign_tier_discount_product']['product']['product_name']??$promo['promo_campaign_tier_discount_product']['product_group']['product_group_name']??'';

	        		$data['detail_rule_tier_discount'] = $promo['promo_campaign_tier_discount_rules'];
	        		foreach ($data['detail_rule_tier_discount'] as $key => $value) {
	        			unset(
	        				$data['detail_rule_tier_discount'][$key]['id_promo_campaign_tier_discount_rule'],
	        				$data['detail_rule_tier_discount'][$key]['id_promo_campaign']
	        			);
	        		}
	        		if ($data['detail_rule_tier_discount'] == [] ) {
	        			unset($data['detail_rule_tier_discount']);
	        		}

	        		break;
	        	
	        	case 'Buy X Get Y':
					
					$promo['buy_x_get_y_discount_product_code'] = $promo['promo_campaign_buyxgety_product_requirement']['product']['product_code']??$promo['promo_campaign_buyxgety_product_requirement']['product_group']['product_group_code']??'';
	        		$promo['buy_x_get_y_discount_product_name'] = $promo['promo_campaign_buyxgety_product_requirement']['product']['product_name']??$promo['promo_campaign_buyxgety_product_requirement']['product_group']['product_group_name']??'';

	        		$data['detail_rule_buyxgety_discount'] = [];
	        		foreach ($promo['promo_campaign_buyxgety_rules'] as $key => $value) {
	        			$data['detail_rule_buyxgety_discount'][] = [
	        				'min_qty'	=> $value['min_qty_requirement'],
	        				'max_qty' 	=> $value['max_qty_requirement'],
	        				'discount_type'		=> $value['discount_type'],
	        				'discount_value'	=> $value['discount_value'],
	        				'max_discount' 		=> $value['max_percent_discount'],
	        				'benefit_product_code' => $value['product']['product_code']??$value['product_group']['product_group_code']??'',
	        				'benefit_product_name' => $value['product']['product_name']??$value['product_group']['product_group_name']??'',
	        				'benefit_product_qty'  => $value['benefit_qty']
	        			];
	        		}

	        		if ($data['detail_rule_buyxgety_discount'] == [] ) {
	        			unset($data['detail_rule_buyxgety_discount']);
	        		}
	        		break;
	        	
	        	default:
	        		$data['detail_rule'] = [];
	        		break;
	        }
			
	        unset(
	        	$promo['id_promo_campaign'],
	        	$promo['created_by'],
	        	$promo['last_updated_by'],
	        	$promo['created_by_user'],
	        	$promo['outlets'],
	        	$promo['promo_campaign_product_discount_rules'],
	        	$promo['promo_campaign_product_discount'],
	        	$promo['promo_campaign_tier_discount_rules'],
	        	$promo['promo_campaign_tier_discount_product'],
	        	$promo['promo_campaign_buyxgety_rules'],
	        	$promo['promo_campaign_buyxgety_product_requirement'],
	        	$promo['used_code'],
	        	$promo['promo_campaign_promo_codes'],
	        	$promo['promo_campaign_have_tags'],
	        	$promo['step_complete'],
	        	$promo['created_at'],
	        	$promo['updated_at']
	        );

	        $temp_promo = [];
	        foreach ($promo as $key => $value) {
	        	$temp_promo[] = [$key, $value];
	        }
	        $data['rule'] = $temp_promo;

            $result = [
                'status'  => 'success',
                'result'  => $data
            ];
        } 
        else 
        {
            $result = [
                'status'  => 'fail',
                'messages'  => ['Promo Campaign Not Found']
            ];
        }

        return response()->json($result);
    }

    public function ImportPromoCampaign(ImportRequest $request)
    {
    	$post = $request->json()->all();
    	$promo = $post['data']['rule'];
    	$warning_image_path = 'img/promo-campaign/warning-image/';
    	$errors = [];
    	$warnings = [];
    	db::beginTransaction();

    	// save deals
    	unset(
    		$promo['date_start'], 
    		$promo['date_end']
    	);

    	$promo['used_code'] 			= 0;
    	$promo['step_complete'] 		= 1;
        $promo['created_by'] 			= auth()->user()->id;
        $promo['last_updated_by'] 		= auth()->user()->id;
    	!empty($post['date_start']) 	? $promo['date_start']  = date('Y-m-d H:i:s', strtotime($post['date_start'])) : $errors[] = 'Date start is required';
        !empty($post['date_end']) 		? $promo['date_end'] 	= date('Y-m-d H:i:s', strtotime($post['date_end'])) : $errors[] = 'Date end is required';

		$promo['promo_campaign_warning_image']	= app($this->deals)->uploadImageFromURL($promo['url_promo_campaign_warning_image'], $warning_image_path, 'warning');
		if (!empty($promo['url_promo_campaign_warning_image']) && empty($promo['promo_campaign_warning_image'])) {
			$warnings[] = 'Promo Campaign warning Image url\'s invalid';
		}
		$create = PromoCampaign::create($promo);
		
		if (!$create) {
			db::rollback();
        	return ['status' => 'fail', 'messages' => ['Create Promo Campaign failed']];
		}

		// save tag
		if (!empty($post['data']['tags'])) {
			$tags = array_column($post['data']['tags'], 'tag');
			$saveTags = app($this->promo_campaign)->insertTag('create', $create['id_promo_campaign'], $tags);
			if (!$saveTags) {
				$errors[] = 'Insert tags failed';
			}
		}

		// save outlet
		if ( !empty($post['data']['outlet']) ) 
		{
			$outletCode = [];
			$outletCodeName = [];
			$id_outlet = [];
			foreach ($post['data']['outlet']??[] as $key => $value) {
				$outletCode[] = $value['outlet_code'];
				$outletCodeName[$value['outlet_code']] = $value['outlet_name'];
			}

			$outlet = Outlet::whereIn('outlet_code', $outletCode)->select('id_outlet', 'outlet_code')->get();

			foreach ($outlet as $key => $value) {
				$id_outlet[] = $value['id_outlet'];
				$dataOutlet[]	= [
					'id_outlet' 			=> $value['id_outlet'],
                	'id_promo_campaign'    	=> $create['id_promo_campaign'],
                	'created_at'           	=> date('Y-m-d H:i:s'),
                	'updated_at'           	=> date('Y-m-d H:i:s')
                ];
				unset($outletCodeName[$value['outlet_code']]);
			}

			$saveOutlet = PromoCampaignOutlet::insert($dataOutlet);

			if (!$saveOutlet) {
				$errors[] = 'Insert Promo Campaign Outlet failed';
			}
			foreach ($outletCodeName as $key => $value) {
				$errors[] = 'Outlet '.$key.' - '.$value.' not found';
			}
		}
		// save code
		$generateCode = app($this->promo_campaign)->generateCode('insert', $create['id_promo_campaign'], $create['code_type'], $post['data']['rule']['promo_code']??null, $create['prefix_code'], $create['number_last_code'], $create['total_coupon']);

		if ($generateCode['status'] != 'success') {
            $errors[] = $generateCode['messages']??'Generate promo code failed';
        }

    	switch ($promo['promo_type']) 
        {
        	case 'Product discount':
        		$rule['id_promo_campaign'] 	= $create['id_promo_campaign'];
        		$rule['is_all_product'] 	= $promo['is_all_product'];
        		$rule['discount_type'] 		= $promo['product_discount_type'];
        		$rule['discount_value'] 	= $promo['product_discount_value'];
        		$rule['max_product'] 		= $promo['product_discount_max_qty'];
        		$rule['max_percent_discount'] = $promo['product_discount_max_discount'];
        		$saveRule = PromoCampaignProductDiscountRule::create($rule);

        		if (!empty($post['data']['detail_rule_product_discount'])) 
				{
					$ruleBenefit = [];
					$ruleProductCode = [];
					$ruleProductCodeName = [];
					foreach ( $post['data']['detail_rule_product_discount']??[] as $key => $value ) {
						$ruleProductCode[] = $value['product_code'];
						$ruleProductCodeName[$value['product_code']] = $value['product_name'];
					}

					if ($create['product_type'] == 'single') {
						$ruleProduct = Product::whereIn('product_code', $ruleProductCode)->select('id_product', 'product_code')->get();
					}else{					
						$ruleProduct = ProductGroup::where('product_group_code', $ruleProductCode)->select('id_product_group', 'product_group_code')->get();
					}

					foreach ($ruleProduct as $key => $value) {
						$ruleProductId[$value['product_code']??$value['product_group_code']] = $value['id_product']??$value['product_group_code'];
						unset($ruleProductCodeName[$value['product_code']??$value['product_group_code']]);
					}

	        		foreach ( $post['data']['detail_rule_product_discount']??[] as $key => $value ) {

	        			if ( isset($ruleProductCodeName[$value['product_code']]) ) {
	        				continue;
	        			}

	        			$ruleBenefit[] = [
	        				'id_promo_campaign'	=> $create['id_promo_campaign'],
	        				'product_type'		=> $create['product_type'],
	        				'id_product' 		=> $ruleProductId[$value['product_code']],
	        				'created_at' 		=> date('Y-m-d H:i:s'),
	            			'updated_at' 		=> date('Y-m-d H:i:s')
	        			];
	        		}

					$saveRuleBenefit = PromoCampaignProductDiscount::insert($ruleBenefit);
					foreach ($ruleProductCodeName as $key => $value) {
						$errors[] = 'Product '.$key.' - '.$value.' not found';
					}
				}
        		break;
        	
        	case 'Tier discount':

				$rule['id_promo_campaign'] = $create['id_promo_campaign'];
				$rule['product_type'] = $create['product_type'];

				if ($create['product_type'] == 'single') {
					$rule['id_product'] = Product::where('product_code', $post['data']['rule']['tier_discount_product_code'])->select('id_product')->first()['id_product']??null;
				}else{					
					$rule['id_product'] = ProductGroup::where('product_group_code', $post['data']['rule']['tier_discount_product_code'])->select('id_product_group')->first()['id_product_group']??null;
				}

				if (empty($rule['id_product'])) {
					$errors[] = 'Product '.$post['data']['rule']['tier_discount_product_code'].' - '.$post['data']['rule']['tier_discount_product_name'].' not found';
					break;
				}

				$saveRule = PromoCampaignTierDiscountProduct::create($rule);

        		foreach ($post['data']['detail_rule_tier_discount'] as $key => $value) {

        			$ruleBenefit[] = [
        				'id_promo_campaign'		=> $create['id_promo_campaign'],
        				'min_qty'				=> $value['min_qty'],
        				'max_qty'				=> $value['max_qty'],
        				'discount_type'			=> $value['discount_type'],
        				'discount_value'		=> $value['discount_value'],
        				'max_percent_discount'	=> $value['max_percent_discount'],
        				'created_at' 			=> date('Y-m-d H:i:s'),
	            		'updated_at' 			=> date('Y-m-d H:i:s')
        			];
        		}
        		
        		$saveRuleBenefit = PromoCampaignTierDiscountRule::insert($ruleBenefit);
        		break;
        	
        	case 'Buy X Get Y':

				$rule['id_promo_campaign'] = $create['id_promo_campaign'];
				$rule['product_type'] = $create['product_type'];

				if ($create['product_type'] == 'single') {
					$rule['id_product'] = Product::where('product_code', $post['data']['rule']['buy_x_get_y_discount_product_code'])->select('id_product')->first()['id_product']??null;
				}else{					
					$rule['id_product'] = ProductGroup::where('product_group_code', $post['data']['rule']['buy_x_get_y_discount_product_code'])->select('id_product_group')->first()['id_product_group']??null;
				}

				if (empty($rule['id_product'])) {
					$errors[] = 'Product '.$post['data']['rule']['buy_x_get_y_discount_product_code'].' - '.$post['data']['rule']['buy_x_get_y_discount_product_name'].' not found';
					break;
				}

				$saveRule = PromoCampaignBuyxgetyProductRequirement::create($rule);

				if (!empty($post['data']['detail_rule_buyxgety_discount'])) 
				{
					$ruleBenefit = [];
					$ruleProductCode = [];
					$ruleProductCodeName = [];
					foreach ( $post['data']['detail_rule_buyxgety_discount']??[] as $key => $value ) {
						$ruleProductCode[] = $value['benefit_product_code'];
						$ruleProductCodeName[$value['benefit_product_code']] = $value['benefit_product_name'];
					}

					$ruleProduct = Product::whereIn('product_code', $ruleProductCode)->select('id_product','product_code')->get();

					foreach ($ruleProduct as $key => $value) {
						$ruleProductId[$value['product_code']] = $value['id_product'];
						unset($ruleProductCodeName[$value['product_code']]);
					}

	        		foreach ( $post['data']['detail_rule_buyxgety_discount']??[] as $key => $value ) {

	        			if ( isset($ruleProductCodeName[$value['benefit_product_code']]) ) {
	        				continue;
	        			}

	        			$ruleBenefit[] = [
	        				'id_promo_campaign'		=> $create['id_promo_campaign'],
	        				'min_qty_requirement'	=> $value['min_qty'],
	        				'max_qty_requirement' 	=> $value['max_qty'],
	        				'discount_type'			=> $value['discount_type'],
	        				'discount_value'		=> $value['discount_value'],
	        				'max_percent_discount' 	=> $value['max_discount'],
	        				'benefit_id_product' 	=> $ruleProductId[$value['benefit_product_code']],
	        				'benefit_qty'  			=> $value['benefit_product_qty'],
	        				'created_at' 			=> date('Y-m-d H:i:s'),
	            			'updated_at' 			=> date('Y-m-d H:i:s')
	        			];
	        		}

					$saveRuleBenefit = PromoCampaignBuyxgetyRule::insert($ruleBenefit);

					foreach ($ruleProductCodeName as $key => $value) {
						$errors[] = 'Product '.$key.' - '.$value.' not found';
					}
				}
        		break;
        	
        	default:
        		$errors[] = 'Deals rules not found';
        		break;
        }

        if (!$saveRuleBenefit || !$saveRule) {
			$errors[] = 'Create Rule failed';
		}

        if (!empty($errors)) {
        	db::rollback();
        	return ['status' => 'fail', 'messages' => $errors];
        }

        db::commit();
        $result = [
        	'status' => 'success', 
        	'messages' => ['Promo Campaign has been imported'],
        	'promo'	=> ['id_promo_campaign' => $create['id_promo_campaign'], 'created_at'  => $create['created_at']]
        ];
        if (!empty($warnings)) {
        	$result['warning'] = $warnings;
        }
    	return $result;
    }
}

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
	protected $index_key;

	function __construct() {
        date_default_timezone_set('Asia/Jakarta');

        $this->online_transaction   = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->deals 		= "Modules\Deals\Http\Controllers\ApiDeals";
        $this->voucher 		= "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->fraud   		= "Modules\SettingFraud\Http\Controllers\ApiFraud";
        $this->index_key 	= [
    		'deals_title' 				=> 'deals title',
    		'deals_second_title' 		=> 'deals second title',
    		'type' 						=> 'type',
    		'deals_description' 		=> 'deals description',
    		'deals_start' 				=> 'deals start',
    		'deals_end' 				=> 'deals end',
    		'deals_publish_start' 		=> 'deals publish start',
    		'deals_publish_end' 		=> 'deals publish end',
    		'url_deals_image' 			=> 'deals image',
    		'is_all_outlet' 			=> 'all outlet',
    		'custom_outlet_text' 		=> 'custom outlet text',
    		'deals_price_type' 			=> 'price type',
    		'deals_price_value' 		=> 'price value',
    		'deals_voucher_type'		=> 'voucher type',
    		'deals_total_voucher' 		=> 'total voucher',
    		'deals_voucher_start' 		=> 'voucher start date',
    		'deals_voucher_expiry' 		=> 'voucher expiry',
    		'deals_voucher_expiry_value'=> 'voucher expiry value',
    		'user_limit' 				=> 'user limit',
    		'url_deals_warning_image' 	=> 'deals warning image',
    		'deals_promo_id_type' 		=> 'offline promo type',
    		'deals_promo_id' 			=> 'offline promo value',
    		'product_type' 				=> 'online product type',
    		'promo_type' 				=> 'online promo type',
    		'product_discount_type' 	=> 'online product discount type',
    		'product_discount_value' 	=> 'online product discount value',
    		'product_discount_max_qty' 	=> 'online product discount max qty',
    		'product_discount_max_discount' => 'online product discount max discount',
    		'is_all_product' 				=> 'online product discount all product',
    		'tier_discount_product_code' 	=> 'online tier discount product code',
    		'tier_discount_product_name' 	=> 'online tier discount product name',
    		'buy_x_get_y_discount_product_code' => 'online buy x get y discount product code',
    		'buy_x_get_y_discount_product_name' => 'online buy x get y discount product name',
    	];
    }

    public function exportPromoCampaign(ExportRequest $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $promo = PromoCampaign::with(
                            'promo_campaign_have_tags.promo_campaign_tag',
                            'promo_campaign_product_discount_rules',
                            'promo_campaign_product_discount',
                            'promo_campaign_tier_discount_rules',
                            'promo_campaign_tier_discount_product',
                            'promo_campaign_buyxgety_rules.product',
                            'promo_campaign_buyxgety_product_requirement',
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

        if ( (isset($saveRuleBenefit) && !$saveRuleBenefit) || (isset($saveRule) && !$saveRule) ) {
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

    public function convertDealsInput($array_deals, $convert_type='export', $input_type=null)
    {
    	$index_key = $this->index_key;

    	switch ($input_type) {

    		case 'date':

				if ($convert_type == 'export') 
				{
					$new_date = [
						'deals_start' 			=> null,
						'deals_end' 			=> null,
						'deals_publish_start' 	=> null,
						'deals_publish_end' 	=> null,
						'deals_voucher_start'	=> null,
						'deals_voucher_expired'	=> null
					];

					foreach ($new_date as $key => $value) {

						if (!empty($array_deals[$key])) {
							$new_data[$key] = date('d F Y', strtotime($array_deals[$key]));
						}
						else{
							$new_data[$key] = null;
						}
					}

				}

    			break;
    		
    		case 'type':

				if ($convert_type == 'export') 
				{
					$type = [];
		    		$array_deals['is_online']? $type[] = 'online' : '';
		    		$array_deals['is_offline']? $type[] = 'offline' : '';
		    		$new_data['type'] = implode(',', $type);
				}
				else{
					$type = $array_deals['type'];
		    		$type = explode(',', $type);
		    		$type = array_map('strtolower', $type);
		    		$new_data['is_online']	= in_array('online', $type) ? 1 : 0;
		    		$new_data['is_offline']	= in_array('offline', $type) ? 1 : 0;
				}

    			break;
    		
    		case 'outlet':

				if ($convert_type == 'export') 
				{
		    		$new_data['is_all_outlet'] = $array_deals['is_all_outlet'] ? 'yes' : 'no';
				}
				else
				{
					$new_data['is_all_outlet'] = $array_deals['is_all_outlet'] == 'yes' ? 1 : 0;
				}

    			break;
    		
    		case 'price':

				if ($convert_type == 'export') 
				{
					$price_type = "free";
		    		$price_value = "";
					if ($array_deals['deals_voucher_price_point']) {
				        $price_type = "point";
				        $price_value = $array_deals['deals_voucher_price_point'];
				    }
				    else if ($array_deals['deals_voucher_price_cash']) {
				        $price_type = "money";
				        $price_value = $array_deals['deals_voucher_price_cash'];
				    }
				    $new_data['deals_price_type'] = $price_type;
    				$new_data['deals_price_value'] = $price_value;
				}
				else
				{
					$price_type = strtolower($array_deals['deals_price_type']);
					if ($price_type == 'point') {
						$new_data['deals_voucher_price_point']	= $array_deals['deals_price_value'];
						$new_data['deals_voucher_price_cash']	= null;
					}
					elseif ($price_type == 'money') {
						$new_data['deals_voucher_price_point']	= null;
						$new_data['deals_voucher_price_cash']	= $array_deals['deals_price_value'];
					}
					else{
						$new_data['deals_voucher_price_point']	= null;
						$new_data['deals_voucher_price_cash']	= null;	
					}
				}

    			break;
    		
    		case 'expiry':

				if ($convert_type == 'export') 
				{
					$expired_type = '';
		    		$expired_value = '';
		    		if ($array_deals['deals_voucher_expired']) {
				        $expired_type = "date";
				        $expired_value = $array_deals['deals_voucher_expired'];
				    }
				    else if ($array_deals['deals_voucher_duration']) {
				        $expired_type = "duration";
				        $expired_value = $array_deals['deals_voucher_duration'];
				    }

		    		$new_data['deals_voucher_expiry'] = $expired_type;
		    		$new_data['deals_voucher_expiry_value'] = $expired_value;
				}

    			break;
    		
    		case 'offline rule':

				if ($convert_type == 'export') 
				{
					$promo_type 	= '';
		    		$promo_value 	= '';
		    		switch ($array_deals['deals_promo_id_type']) {
		    			case 'promoid':
		    				$rule['deals_promo_id_type'] 	= "promo id";
		    				$rule['deals_promo_id'] 		= $array_deals['deals_promo_id'];
		    				break;
		    			
		    			case 'nominal':
		    				$rule['deals_promo_id_type'] 	= "nominal";
		    				$rule['deals_promo_id'] 		= $array_deals['deals_promo_id'];
		    				break;

		    			default:
		    				$rule = [];
		    				break;
		    		}

		    		$new_data	= $rule;
				}
				else
				{
					$promo_type 	= '';
		    		$promo_value 	= '';
		    		switch ($array_deals['deals_promo_id_type']) {
		    			case 'promo id':
		    				$rule['deals_promo_id_type'] 	= "promoid";
		    				$rule['deals_promo_id'] 		= $array_deals['deals_promo_id'];
		    				break;
		    			
		    			case 'nominal':
		    				$rule['deals_promo_id_type'] 	= "nominal";
		    				$rule['deals_promo_id'] 		= $array_deals['deals_promo_id'];
		    				break;

		    			default:
		    				$rule = [];
		    				break;
		    		}

		    		$new_data	= $rule;
				}

    			break;
    		
    		case 'online rule':

				if ($convert_type == 'export') 
				{
					switch ($array_deals['promo_type']) {
		    			case 'Product discount':
		    				$rule['product_type'] 					= $array_deals['product_type'];
		    				$rule['promo_type'] 					= $array_deals['promo_type'];
		    				$rule['product_discount_type'] 			= $array_deals['product_discount_type'];
		    				$rule['product_discount_value'] 		= $array_deals['product_discount_value'];
		    				$rule['product_discount_max_qty'] 		= $array_deals['product_discount_max_qty'];
		    				$rule['product_discount_max_discount'] 	= $array_deals['product_discount_max_discount'];
		    				$rule['is_all_product'] 				= $array_deals['is_all_product'] ? 'yes' : 'no';
		    				break;
		    			
		    			case 'Tier discount':
		    				$rule['product_type'] 				= $array_deals['product_type'];
		    				$rule['promo_type'] 				= $array_deals['promo_type'];
		    				$rule['tier_discount_product_code'] = $array_deals['tier_discount_product_code'];
		    				$rule['tier_discount_product_name'] = $array_deals['tier_discount_product_name'];
		    				break;
		    			
		    			case 'Buy X Get Y':
		    				$rule['product_type'] 						= $array_deals['product_type'];
		    				$rule['promo_type'] 						= $array_deals['promo_type'];
		    				$rule['buy_x_get_y_discount_product_code'] 	= $array_deals['buy_x_get_y_discount_product_code'];
		    				$rule['buy_x_get_y_discount_product_name'] 	= $array_deals['buy_x_get_y_discount_product_name'];
		    				break;
		    			
		    			default:
		    				$rule = [];
		    				break;
		    		}

		    		$new_data = $rule;
				}
				else
				{
					switch ($array_deals['promo_type']) {
		    			case 'Product discount':
		    				$rule['product_type'] 					= $array_deals['product_type'];
		    				$rule['promo_type'] 					= $array_deals['promo_type'];
		    				$rule['product_discount_type'] 			= $array_deals['product_discount_type'];
		    				$rule['product_discount_value'] 		= $array_deals['product_discount_value'];
		    				$rule['product_discount_max_qty'] 		= $array_deals['product_discount_max_qty'];
		    				$rule['product_discount_max_discount'] 	= $array_deals['product_discount_max_discount'];
		    				$rule['is_all_product'] 				= $array_deals['is_all_product'] == 'yes' ? 1 : 0;
		    				break;
		    			
		    			case 'Tier discount':
		    				$rule['product_type'] 				= $array_deals['product_type'];
		    				$rule['promo_type'] 				= $array_deals['promo_type'];
		    				$rule['tier_discount_product_code'] = $array_deals['tier_discount_product_code'];
		    				$rule['tier_discount_product_name'] = $array_deals['tier_discount_product_name'];
		    				break;
		    			
		    			case 'Buy X Get Y':
		    				$rule['product_type'] 						= $array_deals['product_type'];
		    				$rule['promo_type'] 						= $array_deals['promo_type'];
		    				$rule['buy_x_get_y_discount_product_code'] 	= $array_deals['buy_x_get_y_discount_product_code'];
		    				$rule['buy_x_get_y_discount_product_name'] 	= $array_deals['buy_x_get_y_discount_product_name'];
		    				break;
		    			
		    			default:
		    				$rule = [];
		    				break;
		    		}

		    		$new_data = $rule;
				}

    			break;
    		
    		default:

		    	if ($convert_type != 'export') {
		    		$index_key = array_flip($index_key);
		    	}

		    	$new_data = [];
				foreach ($array_deals as $key => $value) {
					$new_data[$index_key[$key]??$key]	= $value;
				}

    			break;
    	}

		return $new_data;
    }

    public function checkDealsInput($array_deals, $check_type='export')
    {
    	if ($check_type == 'export') 
    	{    		
    		$data = $this->index_key;
    		// unset rule
    		unset(
    			$data['is_all_product'],
    			$data['product_type'],
    			$data['promo_type'],
    			$data['product_discount_type'],
    			$data['product_discount_value'],
    			$data['product_discount_max_qty'],
    			$data['product_discount_max_discount'],
    			$data['tier_discount_product_code'],
    			$data['tier_discount_product_name'],
    			$data['buy_x_get_y_discount_product_code'],
    			$data['buy_x_get_y_discount_product_name'],
    			$data['deals_promo_id_type'],
    			$data['deals_promo_id']
    		);

			// date
			$date = $this->convertDealsInput($array_deals, $check_type, 'date');
			$array_deals = array_merge($array_deals, $date);

			// prepare default order data
			foreach ($data as $key => $value) {
				$data[$key] = $array_deals[$key]??'';
			}

			// voucher expiry
			$expiry = $this->convertDealsInput($array_deals, $check_type, 'expiry');
			$data = array_merge($data, $expiry);
    	}
    	else
    	{
    		$data 	= $array_deals;
    	}    	

		// type
		$type = $this->convertDealsInput($array_deals, $check_type, 'type');
		$data = array_merge($data, $type);

		// outlet
		$outlet = $this->convertDealsInput($array_deals, $check_type, 'outlet');
		$data = array_merge($data, $outlet);

		// price type
		$price_type = $this->convertDealsInput($array_deals, $check_type, 'price');
		$data = array_merge($data, $price_type);

		// offline rule
		$offline_rule = $this->convertDealsInput($array_deals, $check_type, 'offline rule');
		$data = array_merge($data, $offline_rule);

		// online rule
		$online_rule = $this->convertDealsInput($array_deals, $check_type, 'online rule');
		$data = array_merge($data, $online_rule);

    	return $data;
    }
}

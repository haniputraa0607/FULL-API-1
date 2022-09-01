<?php

namespace Modules\PromoCampaign\Lib;

use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\PromoCampaign\Entities\PromoCampaignReport;
use Modules\PromoCampaign\Entities\UserReferralCode;
use Modules\PromoCampaign\Entities\PromoCampaignReferralTransaction;
use Modules\PromoCampaign\Entities\PromoCampaignReferral;
use App\Http\Models\Product;
use Modules\ProductVariant\Entities\ProductGroup;
use App\Http\Models\ProductModifier;
use App\Http\Models\UserDevice;
use App\Http\Models\User;
use App\Http\Models\Transaction;
use App\Http\Models\Setting;
use App\Http\Models\Deal;
use App\Http\Models\DealsUser;

use App\Lib\MyHelper;
use DB;

class PromoCampaignTools{

    function __construct()
    {
        $this->user     = "Modules\Users\Http\Controllers\ApiUser";
        $this->promo_campaign     = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->online_transaction   = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
    }
	/**
	 * validate transaction to use promo campaign
	 * @param  	int 		$id_promo 	id promo campaigm
	 * @param  	array 		$trxs      	array of item and total transaction
	 * @param  	array 		$error     	error message
	 * @return 	array/boolean     modified array of trxs if can, otherwise false
	 */
	public function validatePromo($id_promo, $id_outlet, $trxs, &$errors, $source='promo_campaign', $payment_type=null, &$errorProduct=0, $request=null, $delivery_fee=0){
		/**
		 $trxs=[
			{
				id_product:1,
				qty:2
			}
		 ]
		 */
		$is_free = 0;
		if(!is_numeric($id_promo)){
			$errors[]='Id promo not valid';
			return false;
		}
		if(!is_array($trxs)){
			$errors[]='Transaction data not valid';
			return false;
		}

		if ($source == 'promo_campaign')
		{
			$promo=PromoCampaign::with('promo_campaign_outlets')->find($id_promo);
			$promo_outlet = $promo->promo_campaign_outlets;
			$promo_title = $promo['promo_title'];
		}
		elseif($source == 'deals')
		{
			$promo=Deal::with('outlets_active')->find($id_promo);
			$promo_outlet = $promo->outlets_active;
			$promo_title = $promo['deals_title'];
		}
		else
		{
			$errors[]='Promo not found';
			return false;
		}

		if(!$promo){
			$errors[]='Promo not found';
			return false;
		}

		$outlet = $this->checkOutletRule($id_outlet, $promo->is_all_outlet??0, $promo_outlet);

		if(!$outlet){
			$errors[]='Promo cannot be used at this outlet';
			return false;
		}

		if (isset($request['type'])) {
			if ($promo->promo_type == 'Discount delivery') {

				$available_shipment = $this->getAvailableShipment($id_outlet);
				$shipment_method = $promo->{$source.'_shipment_method'}->pluck('shipment_method');
				$promo_shipment = [];

				foreach ($shipment_method as $value) {
					if ($value == 'Pickup Order') {
						$promo_shipment[] = $value;
					}
		            if (isset($available_shipment[$value])) {
		            	$promo_shipment[] = $available_shipment[$value]['type'];
		            }
		        }

				if ($request->type == 'Pickup Order') {
					$errors[]='Promo cannot be used for Pickup Order';
					return false;
				}
				if (count($promo_shipment) == 1 && $promo_shipment[0] == 'Pickup Order') {
					$promo->is_all_shipment = 1;
				}
				$check_shipment = $this->checkShipmentRule($promo->is_all_shipment??0, $request->type, $promo_shipment);

				if(!$check_shipment){
					$errors[]='Promo cannot be used for this order type';
					return false;
				}
			}
		}

		if( (!empty($promo->date_start) && !empty($promo->date_end)) && (strtotime($promo->date_start)>time()||strtotime($promo->date_end)<time())){
			$errors[]='Promo is not valid';
			return false;
		}

		$discount=0;
		$subtotal = 0;
		// add product discount if exist
		/*
		*
		* dikomen karena sekarang belum digunakan
		*
		foreach ($trxs as  $id_trx => &$trx) {
			$product=Product::with(['product_prices' => function($q) use ($id_outlet){
							$q->where('id_outlet', '=', $id_outlet)
							  ->where('product_status', '=', 'Active')
							  ->where('product_stock_status', '=', 'Available');
						} ])->find($trx['id_product']);
			//is product available
			if(!$product){
				// product not available
				$errors[]='Product with id '.$trx['id_product'].' could not be found';
				continue;
			}
			$product_discount=$this->getProductDiscount($product)*$trx['qty'];
			$product_price=$product->product_prices[0]->product_price??[];
			// $discount+=$product_discount;
			if($product_discount){
				// $trx['discount']=$product_discount;
				$trx['new_price']=($product_price*$trx['qty'])-$product_discount;
			}
		}
		*/

		//get all modifier in array
		$mod = [];
		foreach ($trxs as $key => $value) {
			foreach ($value['modifiers'] as $key2 => $value2) {
				$mod[] = $value2['id_product_modifier']??$value2;
			}
		}
		// remove duplicate modifiers
		$mod = array_flip($mod);
		$mod = array_flip($mod);
		// get all modifier data
		$mod = $this->getAllModifier($mod, $id_outlet);

		// get mod price
		$mod_price =[];
		foreach ($mod as $key => $value) {
			$mod_price[$value['id_product_modifier']] = $value['product_modifier_price']??0;
		}

		switch ($promo->promo_type) {
			case 'Product discount':
				// load required relationship
				$promo->load($source.'_product_discount',$source.'_product_discount_rules');
				$promo_rules=$promo[$source.'_product_discount_rules'];
				$max_product = $promo_rules->max_product;
				$qty_promo_available = [];

				if(!$promo_rules->is_all_product){
					$promo_product_obj = $promo[$source.'_product_discount'];
					$promo_product = $promo[$source.'_product_discount']->toArray();
				}else{
					$promo_product="*";
				}

				// sum total quantity of same product, if greater than max product assign value to max product
				// get all modifier price total, index array of item, and qty for each modifier
				$item_get_promo = [];
				$item_group_get_promo = [];
				$mod_price_per_item = [];
				$mod_price_qty_per_item = [];
				($promo['product_type'] == 'group') ? $id = 'id_product_group' : $id = 'id_product';

				foreach ($trxs as $key => $value)
				{
					// count item get promo qty
					if (isset($item_get_promo[$value[$id]]))
					{
						if ( ($item_get_promo[$value[$id]] + $value['qty']) >= $max_product && !empty($max_product)) {
							$item_get_promo[$value[$id]] = $max_product;
						}else{
							$item_get_promo[$value[$id]] += $value['qty'];
						}
					}
					else
					{
						if ($value['qty'] >= $max_product  && !empty($max_product)) {
							$item_get_promo[$value[$id]] = $max_product;
						}else{
							$item_get_promo[$value[$id]] = $value['qty'];
						}
					}

					// count item group get promo qty
					if (isset($item_group_get_promo[$value['id_product_group']]))
					{
						if ( ($item_group_get_promo[$value['id_product_group']] + $value['qty']) >= $max_product  && !empty($max_product)) {
							$item_group_get_promo[$value['id_product_group']] = $max_product;
						}else{
							$item_group_get_promo[$value['id_product_group']] += $value['qty'];
						}
					}
					else
					{
						if ($value['qty'] >= $max_product && !empty($max_product)) {
							$item_group_get_promo[$value['id_product_group']] = $max_product;
						}else{
							$item_group_get_promo[$value['id_product_group']] = $value['qty'];
						}
					}
					// count modifier for each item
					$mod_price_qty_per_item[$value[$id]][$key] = [];
					$mod_price_qty_per_item[$value[$id]][$key]['qty'] = $value['qty'];
					$mod_price_qty_per_item[$value[$id]][$key]['price'] = 0;

					$mod_price_per_item[$value[$id]][$key] = 0;
					foreach ($value['modifiers'] as $key2 => $value2)
					{
						$mod_price_qty_per_item[$value[$id]][$key]['price'] += ($mod_price[$value2['id_product_modifier']??$value2]??0)*($value2['qty']??1);
						$mod_price_per_item[$value[$id]][$key] += ($mod_price[$value2['id_product_modifier']??$value2]??0)*($value2['qty']??1);
					}
				}

				// sort mod price qty ascending
				foreach ($mod_price_qty_per_item as $key => $value) {

					//sort price only to get index key
					asort($mod_price_per_item[$key]);

					// sort mod by price
					$keyPositions = [];
					foreach ($mod_price_per_item[$key] as $key2 => $row) {
						$keyPositions[] = $key2;
					}

					foreach ($value as $key2 => $row) {
					    $price[$key][$key2]  = $row['price'];
					}
					array_multisort($price[$key], SORT_ASC, $value);


					$sortedArray = [];
					foreach ($value as $key2 => $row) {
					    $sortedArray[$keyPositions[$key2]] = $row;
					}

					// assign sorted value to current mod key
					$mod_price_qty_per_item[$key] = $sortedArray;
				}

				foreach ($mod_price_qty_per_item as $key => $value)
				{
					foreach ($value as $key2 => &$value2)
					{

						if ($value2['qty'] > 0) {
							if (($item_get_promo[$key] - $value2['qty']) > 0)
							{
								$trxs[$key2]['promo_qty'] = $value2['qty'];
								$item_get_promo[$key] -= $value2['qty'];
							}
							else
							{
								$trxs[$key2]['promo_qty'] = $item_get_promo[$key];
								$item_get_promo[$key] = 0;
							}
						}
					}
				}
				$promo_detail = [];
				foreach ($trxs as  $id_trx => &$trx) {
					// continue if qty promo for same product is all used
					if ($trx['promo_qty'] == 0) {
						continue;
					}
					$modifier = 0;
					foreach ($trx['modifiers'] as $key2 => $value2)
					{
						$modifier += ($mod_price[$value2['id_product_modifier']??$value2]??0) * ($value2['qty']??1);
					}

					// is all product get promo
					if($promo_rules->is_all_product){
						// get product data
						// $product = $this->getOneProduct($id_outlet, $trx['id_product']);
						$product = $this->getProductPrice($id_outlet, $trx['id_product'], $trx['id_brand'], $trx['id_product_group']);
						//is product available
						if(!$product){
							// product not available
							$errors[]='Product could not be found';
							continue;
						}

						// add discount
						$discount+=$this->discount_product($product,$promo_rules,$trx, $modifier);
					}else{

						// is product available in promo
						if ($promo['product_type'] == 'group')
						{
							if(!is_array($promo_product) || !in_array($trx['id_product_group'],array_column($promo_product,'id_product'))){
								continue;
							}
						}
						else
						{
							if(!is_array($promo_product) || !in_array($trx['id_product'],array_column($promo_product,'id_product'))){
								continue;
							}
						}

						// get product data
						// $product = $this->getOneProduct($id_outlet, $trx['id_product']);
						$product = $this->getProductPrice($id_outlet, $trx['id_product'], $trx['id_brand'], $trx['id_product_group']);
						
						//is product available
						if(!$product){
							// product not available
							$errors[]='Product could not be found';
							continue;
						}

						if ( empty($promo_detail[$product['id_product']]) ) {
							$product->load('product_group', 'product_variants');
							$promo_detail[$product['id_product']]['name'] = $this->getProductName($product->product_group, $product->product_variants);
							$promo_detail[$product['id_product']]['promo_qty'] = $trx['promo_qty'];
						}else{
							$promo_detail[$product['id_product']]['promo_qty'] = $promo_detail[$product['id_product']]['promo_qty'] + $trx['promo_qty'];
						}
						// add discount
						$discount+=$this->discount_product($product,$promo_rules,$trx, $modifier);
					}
				}

				if($discount<=0){
					if (count($promo_product) == 1) {
						if ($promo['product_type'] == 'group') {
							$product_name = $this->getProductName($promo_product_obj[0]->product_group);
						}else{
							$promo_product_obj[0]->product->load('product_group', 'product_variants')->toArray();
							$product_name = $this->getProductName($promo_product_obj[0]->product->product_group, $promo_product_obj[0]->product->product_variants);
						}
					}else{
						$product_name = 'specially marked product';
					}
					$message = $this->getMessage('error_product_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=> $product_name, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}


				/** add new description & promo detail **/
				$product = app($this->promo_campaign)->getProduct($source, $promo);
				if ($promo_rules->discount_type == 'Percent') {
	        		$discount_benefit = ($promo_rules['discount_value']??0).'%';
	        	}else{
	        		$discount_benefit = 'IDR '.number_format(($promo_rules['discount_value']??0),0,',','.');
	        	}

				if($promo_rules->is_all_product || count($promo_detail) > 3)
				{
					$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for '.$product['product'];
				}
				else
				{
					$new_description = "";
					foreach ($promo_detail as $key => $value) {
						$new_description .= $value['name']." (".$value['promo_qty']."x)\n";
					}

					if ($new_description != "") {
						$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for '.substr($new_description, 0);
					}
				}

				$promo_detail_message = 'Discount '.$discount_benefit;
				/** end add new description & promo detail **/

				break;
			case 'Tier discount':

				// load requirement relationship
				$promo->load($source.'_tier_discount_rules',$source.'_tier_discount_product');
				$promo_product=$promo[$source.'_tier_discount_product'];
				($promo['product_type'] == 'group') ? $id = 'id_product_group' : $id = 'id_product';

				if(!$promo_product){
					$errors[]='Tier discount promo product is not set correctly';
					return false;
				}
				// sum total quantity of same product
				foreach ($trxs as $key => $value)
				{
					if (isset($item_get_promo[$value[$id]]))
					{
						$item_get_promo[$value[$id]] += $value['qty'];
					}
					else
					{
						$item_get_promo[$value[$id]] = $value['qty'];
					}
				}

				// get min max required for error message
				$promo_rules=$promo[$source.'_tier_discount_rules'];
				$min_qty = 1;
				$max_qty = 1;
				foreach ($promo_rules as $rule) {
					if($min_qty===null||$rule->min_qty<$min_qty){
						$min_qty=$rule->min_qty;
					}
					if($max_qty===null||$rule->max_qty>$max_qty){
						$max_qty=$rule->max_qty;
					}
				}

				if ($promo['product_type'] == 'group') {
					$product_name = $this->getProductName($promo_product->product_group);
				}else{
					$promo_product->product->load('product_group', 'product_variants')->toArray();
					$product_name = $this->getProductName($promo_product->product->product_group, $promo_product->product->product_variants);
				}

				// promo product not available in cart?
				if(!in_array($promo_product->id_product, array_column($trxs, $id))){
					$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
					$message = $this->getMessage('error_tier_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$product_name, 'minmax'=>$minmax, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}

				//get cart's product to apply promo
				$product=null;
				foreach ($trxs as &$trx) {
					//is this the cart product we looking for?
					if($trx[$id]==$promo_product->id_product){
						//set reference to this cart product
						$product=&$trx;
						// break from loop
						break;
					}
				}
				// product not found? buat jaga-jaga kalau sesuatu yang tidak diinginkan terjadi
				if(!$product){
					$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
					$message = $this->getMessage('error_tier_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$product_name, 'minmax'=>$minmax, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}

				$product_price = $this->getProductPrice($id_outlet, $trx['id_product'], $trx['id_brand'], $trx['id_product_group']);
				//find promo
				$promo_rule=false;
				$min_qty=null;
				$max_qty=null;
				foreach ($promo_rules as $rule) {
					if($min_qty===null||$rule->min_qty<$min_qty){
						$min_qty=$rule->min_qty;
					}
					if($max_qty===null||$rule->max_qty>$max_qty){
						$max_qty=$rule->max_qty;
					}
					if($rule->min_qty>$item_get_promo[$promo_product->id_product]){
						continue;
					}
					// if($rule->max_qty<$item_get_promo[$promo_product->id_product]){
					// 	continue;
					// }
					$promo_rule=$rule;
				}

				if(!$promo_rule){
					$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
					$message = $this->getMessage('error_tier_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$product_name, 'minmax'=>$minmax, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}
				// count discount
				foreach ($trxs as $key => &$trx) {

					$modifier = 0;
					foreach ($trx['modifiers'] as $key2 => $value2)
					{
						$modifier += ($mod_price[$value2['id_product_modifier']??$value2]??0) * ($value2['qty']??1);
					}

					if($trx[$id]==$promo_product->id_product){
						$trx['promo_qty'] = $max_qty < $trx['qty'] ? $max_qty : $trx['qty'];
						$discount+=$this->discount_product($product_price,$promo_rule,$trx, $modifier);
					}
				}
				$product_benefit = app($this->promo_campaign)->getProduct($source, $promo);

				if ($promo_rule->discount_type == 'Percent') {
	        		$discount_benefit = ($promo_rule['discount_value']??0).'%';
	        	}else{
	        		$discount_benefit = 'IDR '.number_format(($promo_rule['discount_value']??0),0,',','.');
	        	}

				$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for '.$product_benefit['product'];
				$promo_detail_message = 'Discount '.$discount_benefit;

				break;

			case 'Buy X Get Y':
				// load requirement relationship
				$promo->load($source.'_buyxgety_rules',$source.'_buyxgety_product_requirement');
				$promo_product=$promo[$source.'_buyxgety_product_requirement'];
				($promo['product_type'] == 'group') ? $id = 'id_product_group' : $id = 'id_product';
				// $promo_product->load('product');

				if(!$promo_product){
					$errors[]='Benefit product is not set correctly';
					return false;
				}

				// sum total quantity of same product
				foreach ($trxs as $key => $value)
				{
					if (isset($item_get_promo[$value[$id]]))
					{
						$item_get_promo[$value[$id]] += $value['qty'];
					}
					else
					{
						$item_get_promo[$value[$id]] = $value['qty'];
					}
				}

				$promo_rules=$promo[$source.'_buyxgety_rules'];
				$min_qty=1;
				$max_qty=1;
				// get min max for error message
				foreach ($promo_rules as $rule) {

					if($min_qty===null||$rule->min_qty_requirement<$min_qty){
						$min_qty=$min_qty;
					}
					if($max_qty===null||$rule->max_qty_requirement>$max_qty){
						$max_qty=$max_qty;
					}
				}
				
				if ($promo['product_type'] == 'group') {
					$product_name = $this->getProductName($promo_product->product_group);
				}else{
					$promo_product->product->load('product_group', 'product_variants')->toArray();
					$product_name = $this->getProductName($promo_product->product->product_group, $promo_product->product->product_variants);
				}
				// promo product not available in cart?
				if(!in_array($promo_product->id_product, array_column($trxs, $id))){
					$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
					$message = $this->getMessage('error_buyxgety_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$product_name, 'minmax'=>$minmax, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}
				//get cart's product to get benefit
				$product=null;
				foreach ($trxs as &$trx) {
					//is this the cart product we looking for?
					if($trx[$id]==$promo_product->id_product){
						//set reference to this cart product
						$ref_item = &$trx;
						$product=&$trx;
						// break from loop
						break;
					}
				}
				// product not found? buat jaga-jaga kalau sesuatu yang tidak diinginkan terjadi
				if(!$product){
					$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
					$message = $this->getMessage('error_buyxgety_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$product_name, 'minmax'=>$minmax, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}
				//find promo
				$promo_rules=$promo[$source.'_buyxgety_rules'];
				$promo_rule=false;
				$min_qty=null;
				$max_qty=null;

				foreach ($promo_rules as $rule) {
					// search y product in cart
					$benefit_qty=$rule->benefit_qty;
					$min_req=$rule->min_qty_requirement;
					$max_req=$rule->max_qty_requirement;

					if($min_qty===null||$rule->min_qty_requirement<$min_qty){
						$min_qty=$min_req;
					}
					if($max_qty===null||$rule->max_qty_requirement>$max_qty){
						$max_qty=$max_req;
					}
					if($min_req>$item_get_promo[$promo_product->id_product]){
						continue;
					}
					// if($max_req<$item_get_promo[$promo_product->id_product]){
					// 	continue;
					// }
					$promo_rule=$rule;
				}
				if(!$promo_rule){
					$minmax=$min_qty!=$max_qty?"$min_qty - $max_qty":$min_qty;
					$message = $this->getMessage('error_buyxgety_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$product_name, 'minmax'=>$minmax, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}
				$benefit_product = $this->getOneProduct($id_outlet, $promo_rule->benefit_id_product,1, 1);

				if(!$benefit_product){
					$errors[]="Product benefit not found.";
					return false;
				}
				$benefit=null;

				$benefit_product_price = $this->getProductPrice(
					$id_outlet, 
					$promo_rule->benefit_id_product, 
					$promo->id_brand??$benefit_product->brands[0]->id_brand??'', 
					$benefit_product->id_product_group
				);
				// dd($benefit_product_price->toArray());
				$ref_item['is_promo'] = 1;
				$benefit_qty=$promo_rule->benefit_qty;
				$benefit_value=$promo_rule->discount_value;
				$benefit_type = $promo_rule->discount_type;
				$benefit_max_value = $promo_rule->max_percent_discount;


				$rule=(object) [
					'max_qty'=>$benefit_qty,
					'discount_type'=>$benefit_type,
					'discount_value'=>$benefit_value,
					'max_percent_discount'=>$benefit_max_value
				];
				// add product benefit
				$benefit_item = [
					'id_custom' 	=> isset(end($trxs)['id_custom']) ? end($trxs)['id_custom']+1 : '',
					'id_product'	=> $benefit_product->id_product,
					'id_brand'		=> $promo->id_brand??$benefit_product->brands[0]->id_brand??'',
					'qty'			=> $promo_rule->benefit_qty,
					'is_promo'		=> 1,
					'is_free'		=> ($promo_rule->discount_type == "percent" && $promo_rule->discount_value == 100) ? 1 : 0,
					'variants'		=> [$benefit_product->product_variants[1]->id_product_variant??'', $benefit_product->product_variants[0]->id_product_variant??''],
					'modifiers'		=> [],
					'bonus'			=> 1
				];

				$discount+=$this->discount_product($benefit_product_price,$rule,$benefit_item);

				array_push($trxs, $benefit_item);

				// $product['product'] = $benefit_product->product_name;
				$product['product'] = $this->getProductName($benefit_product->product_group, $benefit_product->product_variants);
				if ($promo_rule->discount_type == 'Percent' || $promo_rule->discount_type == 'percent') {
					if ($promo_rule->discount_value == 100) {
						$new_description = 'You get '.$promo_rule['benefit_qty'].' '.$product['product'].' Free';
						$promo_detail_message = 'Free '.$product['product'].' ('.$promo_rule['benefit_qty'].'x)';
						$is_free = 1;
					}else{
		        		$discount_benefit = ($promo_rule['discount_value']??0).'%';
		        		$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for '.$product['product'];
						$promo_detail_message = 'Discount '.$discount_benefit;
					}

	        	}else{
	        		$discount_benefit = 'IDR '.number_format(($promo_rule['discount_value']??0),0,',','.');
					$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for '.$product['product'];
					$promo_detail_message = 'Discount '.$discount_benefit;
	        	}

				break;
				
			case 'Promo Product Category':
				// load requirement relationship
				$promo->load($source.'_productcategory_rules',$source.'_productcategory_category_requirements');
				$promo_product=$promo[$source.'_productcategory_category_requirements'];
				$product_group = ProductGroup::where('id_product_category',$promo_product['id_product_category'])->get()->toArray();
				$product_group = array_pluck($product_group,'id_product_group');
				$id = 'id_product_category';
				// $promo_product->load('product');

				if(!$promo_product){
					$errors[]='Benefit product is not set correctly';
					return false;
				}
				// sum total quantity of same product
				$trx_category = [];
				foreach ($trxs as $key => $value)
				{
					if(in_array($value['id_product_group'],$product_group)){
						$value[$id] = $promo_product['id_product_category'];
						$trx_category[] = $value;
					}else{
						$value[$id] = null;
					}
					$trxs[$key][$id] = $value[$id];
					
					if (isset($item_get_promo[$value[$id]]))
					{
						$item_get_promo[$value[$id]] += $value['qty'];
					}
					else
					{
						$item_get_promo[$value[$id]] = $value['qty'];
					}
				
				}
				
				$promo_rules=$promo[$source.'_productcategory_rules'];
				$min_qty=$promo_rules[0]['min_qty_requirement'];
				// get min max for error message
				// foreach ($promo_rules as $rule) {

				// 	if($min_qty===null||$rule->min_qty_requirement<$min_qty){
				// 		$min_qty=$min_qty;
				// 	}
				// }

				$category_name = $promo_product->product_category->product_category_name;

				// promo product not available in cart?
				if(!in_array($promo_product->id_product_category, array_column($trxs, $id))){
					$message = $this->getMessage('error_productcategory_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					return $message = MyHelper::simpleReplace($message,['product'=>$category_name, 'minmax'=>$min_qty, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}
				
				//get cart's product to get benefit
				$category=null;
				foreach ($trxs as &$trx) {
					//is this the cart product we looking for?
					if($trx[$id]==$promo_product->id_product_category){
						//set reference to this cart product
						$ref_item = &$trx;
						$category=&$trx;
						// break from loop
						break;
					}
				}
				// product not found? buat jaga-jaga kalau sesuatu yang tidak diinginkan terjadi
				if(!$category){
					$message = $this->getMessage('error_productcategory_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$category_name, 'minmax'=>$min_qty, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}
				//find promo
				$promo_rules=$promo[$source.'_productcategory_rules'];
				$promo_rule=false;
				$min_qty=null;
				$max_qty=null;
				
				foreach ($promo_rules as $rule) {
					// search y product in cart
					$benefit_qty=$rule->benefit_qty;
					$min_req=$rule->min_qty_requirement;

					if($min_qty===null||$rule->min_qty_requirement<$min_qty){
						$min_qty=$min_req;
					}
					if($min_req>$item_get_promo[$promo_product->id_product_category]){
						continue;
					}
					$promo_rule=$rule;
				}
				
				if(!$promo_rule){
					$message = $this->getMessage('error_productcategory_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product%</b> sebanyak <b>%minmax%</b>.';
					$message = MyHelper::simpleReplace($message,['product'=>$category_name, 'minmax'=>$min_qty, 'title' => $promo_title]);

					$errors[]= $message;
					$errorProduct = 1;
					return false;
				}
				$benefit_products = [];

				foreach($trx_category ?? [] as $trx_cat){
					$benefit_products[] = $this->getOneProduct($id_outlet, $trx_cat['id_product'],1, 1);
				}

				if(!$benefit_products){
					$errors[]="Product benefit not found.";
					return false;
				}
				$benefit_product = array_reduce($benefit_products, function($a, $b){
					return $a['product_price'] < $b['product_price'] ? $a : $b;
				}, array_shift($benefit_products));
				
				$benefit=null;

				$benefit_product_price = $this->getProductPrice(
					$id_outlet, 
					$benefit_product->id_product, 
					$promo->id_brand??$benefit_product->brands[0]->id_brand??'', 
					$benefit_product->id_product_group
				);
				// dd($benefit_product_price->toArray());
				$ref_item['is_promo'] = 1;
				$benefit_qty=$promo_rule->benefit_qty;
				$benefit_value=$promo_rule->discount_value;
				$benefit_type = $promo_rule->discount_type;
				$benefit_max_value = $promo_rule->max_percent_discount;


				$rule=(object) [
					'max_qty'=>$benefit_qty,
					'discount_type'=>$benefit_type,
					'discount_value'=>$benefit_value,
					'max_percent_discount'=>$benefit_max_value
				];
				// add product benefit
				$benefit_item = [
					'id_custom' 	=> isset(end($trxs)['id_custom']) ? end($trxs)['id_custom']+1 : '',
					'id_product'	=> $benefit_product->id_product,
					'id_brand'		=> $promo->id_brand??$benefit_product->brands[0]->id_brand??'',
					'qty'			=> $promo_rule->benefit_qty,
					'is_promo'		=> 1,
					'is_free'		=> ($promo_rule->discount_type == "percent" && $promo_rule->discount_value == 100) ? 1 : 0,
					'variants'		=> [$benefit_product->product_variants[1]->id_product_variant??'', $benefit_product->product_variants[0]->id_product_variant??''],
					'modifiers'		=> [],
					'bonus'			=> 1
				];

				$discount+=$this->discount_product($benefit_product_price,$rule,$benefit_item);

				array_push($trxs, $benefit_item);

				// $product['product'] = $benefit_product->product_name;
				$product['product'] = $this->getProductName($benefit_product->product_group, $benefit_product->product_variants);
				if ($promo_rule->discount_type == 'Percent' || $promo_rule->discount_type == 'percent') {
					if ($promo_rule->discount_value == 100) {
						$new_description = 'You get '.$promo_rule['benefit_qty'].' '.$product['product'].' Free';
						$promo_detail_message = 'Free '.$product['product'].' ('.$promo_rule['benefit_qty'].'x)';
						$is_free = 1;
					}else{
						$discount_benefit = ($promo_rule['discount_value']??0).'%';
						$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for '.$product['product'];
						$promo_detail_message = 'Discount '.$discount_benefit;
					}

				}else{
					$discount_benefit = 'IDR '.number_format(($promo_rule['discount_value']??0),0,',','.');
					$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for '.$product['product'];
					$promo_detail_message = 'Discount '.$discount_benefit;
				}

				break;
				
			case 'Discount global':
				// load required relationship
				$promo->load('promo_campaign_discount_global_rule');
				$promo_rules=$promo->promo_campaign_discount_global_rule;
				// get jumlah harga
				$total_price=0;
				foreach ($trxs as  $id_trx => &$trx) {
					$product=Product::with(['product_prices' => function($q) use ($id_outlet){
							$q->where('id_outlet', '=', $id_outlet)
							  ->where('product_status', '=', 'Active')
							  ->where('product_stock_status', '=', 'Available')
							  ->where('product_visibility', '=', 'Visible');
						} ])->find($trx['id_product']);
					$qty=$trx['qty'];
					$total_price+=$qty*$product->product_prices[0]->product_price??[];
				}
				if($promo_rules->discount_type=='Percent'){
					$discount+=($total_price*$promo_rules->discount_value)/100;
				}else{
					if($promo_rules->discount_value<$total_price){
						$discount += $promo_rules->discount_value;
					}else{
						$discount += $total_price;
					}
					break;
				}
				break;
			case 'Referral':
				$promo->load('promo_campaign_referral');
				$promo_rules=$promo->promo_campaign_referral;
				$rule=(object) [
					'max_qty'=>false,
					'discount_type'=>$promo_rules->referred_promo_unit,
					'discount_value'=>$promo_rules->referred_promo_value,
					'max_percent_discount'=>$promo_rules->referred_promo_value_max
				];
				$grandtotal = 0;
				foreach ($trxs as  $id_trx => &$trx) {
					// get product data
					$product=Product::with(['product_prices' => function($q) use ($id_outlet){
						$q->where('id_outlet', '=', $id_outlet)
						  ->where('product_status', '=', 'Active')
						  ->where('product_stock_status', '=', 'Available');
					} ])->find($trx['id_product']);
					$cur_mod_price = 0;
					foreach ($trx['modifiers'] as $modifier) {
		                $id_product_modifier = is_numeric($modifier)?$modifier:$modifier['id_product_modifier'];
		                $qty_product_modifier = is_numeric($modifier)?1:$modifier['qty'];
		                $cur_mod_price += ($mod_price[$id_product_modifier]??0)*$qty_product_modifier;
					}
					//is product available
					if(!$product){
						// product not available
						$errors[]='Product could not be found';
						continue;
					}
					// add discount
					$product_price = (($product->product_price??$product->product_prices[0]->product_price??null) + $cur_mod_price) * $trx['qty'];

					if(isset($trx['new_price'])&&$trx['new_price']){
						$product_price=$trx['new_price'];
					}
					$grandtotal += $product_price;
					if($promo_rules->referred_promo_type == 'Product Discount'){ 
						$discount += $this->discount_product($product,$rule,$trx,$cur_mod_price);
					}
				}

				if ($grandtotal < $promo_rules->referred_min_value) {
					$errors[] = str_replace(['%min_total%'], [MyHelper::requestNumber($promo_rules->referred_min_value,'_CURRENCY')], 'You can use this promo with a minimum transaction subtotal of %min_total%');
					$errorProduct = 1;
					return false;
				}
				if($promo_rules->referred_promo_type == 'Product Discount'){
					/** add new description & promo detail **/
					if ($promo_rules->discount_type == 'Percent') {
						$discount_benefit = ($promo_rules['discount_value']??0).'%';
					}else{
						$discount_benefit = 'IDR '.number_format(($promo_rules['discount_value']??0),0,',','.');
					}

					$new_description = 'You get discount of IDR '.number_format($discount,0,',','.').' for all Product';

					$promo_detail_message = 'Discount '.$discount_benefit;
					/** end add new description & promo detail **/
				}else{
	            	if (!empty($payment_type) && $payment_type == 'Balance')
	            	{
	            		$new_description = "You will not get cashback benefits by using MAXX Points";
	            	}
	            	else
	            	{
						switch ($promo_rules->referred_promo_unit) {
							case 'Nominal':
								$new_description = 'You will get cashback of IDR '.number_format($promo_rules->referred_promo_value,0,',','.');
							break;
							case 'Percent':
								$new_description = 'You will get cashback of '.number_format($promo_rules->referred_promo_value).'%';
							break;
						}
					}
				return [
					'item'=>$trxs,
					'discount'=>0,
					'new_description' => $new_description??'',
					'promo_detail'		=> '',
					'is_free'			=> $is_free
				];
			}

			case 'Discount delivery':
				// load required relationship
				$promo->load($source.'_discount_delivery_rules');
				$promo_rules = $promo[$source.'_discount_delivery_rules'];

				if ($promo_rules) {
					$discount_delivery = $this->discountDelivery(
						$delivery_fee, 
						$promo_rules->discount_type,
						$promo_rules->discount_value,
						$promo_rules->max_percent_discount
					);
				}

				if ($promo_rules->discount_type == 'Percent') {
	        		$discount_benefit = ($promo_rules['discount_value']??0).'%';
	        	}else{
	        		$discount_benefit = 'IDR '.number_format(($promo_rules['discount_value']??0),0,',','.');
	        	}

	        	$promo_detail_message = 'Discount Delivery costs '.$discount_benefit;
	        	$new_description = 'You get discount Delivery costs '.number_format($discount_delivery,0,',','.');

				break;
		}
		// discount?
		// if($discount<=0){
		// 	$errors[]='Does not get any discount';
		// 	return false;
		// }
		return [
			'item'				=> $trxs,
			'discount'			=> $discount,
			'new_description'	=> $new_description??'',
			'promo_detail'		=> $promo_detail_message,
			'is_free'			=> $is_free,
			'discount_delivery' => $discount_delivery ?? 0
		];
	}

	/**
	 * validate transaction to use promo campaign light version
	 * @param  	int 		$id_promo 	id promo campaigm
	 * @param  	array 		$trxs      	array of item and total transaction
	 * @param  	array 		$error     	error message
	 * @return 	boolean     true/false
	 */

	public static function validatePromoLight($id_promo,$trxs,&$errors){
		/**
		 $trxs=[
			{
				id_product:1,
				qty:2
			}
		 ]
		 */
		if(!is_numeric($id_promo)){
			$errors[]='Id promo not valid';
			return false;
		}
		if(!is_array($trxs)){
			$errors[]='Transaction data not valid';
			return false;
		}
		$promo=PromoCampaign::find($id_promo);
		if(!$promo){
			$errors[]='Promo Campaign not found';
			return false;
		}
		$discount=0;
		switch ($promo->promo_type) {
			case 'Product discount':
				// load required relationship
				$promo->load('promo_campaign_product_discount','promo_campaign_product_discount_rules');
				$promo_rules=$promo->promo_campaign_product_discount_rules;
				if(!$promo_rules->is_all_product){
					$promo_product=$promo->promo_campaign_product_discount->toArray();
				}else{
					$promo_product="*";
				}
				foreach ($trxs as  $id_trx => &$trx) {
					// is all product get promo
					if($promo_rules->is_all_product){
						return true;
					}else{
						// is product available in promo
						if(is_array($promo_product)&&in_array($trx['id_product'],array_column($promo_product,'id_product'))){
							return true;
						}
					}
				}
				return false;
				break;

			case 'Tier discount':
				// load requirement relationship
				$promo->load('promo_campaign_tier_discount_rules','promo_campaign_tier_discount_product');
				$promo_product=$promo->promo_campaign_tier_discount_product;
				if(!$promo_product){
					$errors[]='Tier discount promo product is not set correctly';
					return false;
				}
				// promo product not available in cart?
				if(!in_array($promo_product->id_product, array_column($trxs, 'id_product'))){
					$errors[]='Cart doesn\'t contain promoted product';
					return false;
				}
				//get cart's product to apply promo
				$product=null;
				foreach ($trxs as &$trx) {
					//is this the cart product we looking for?
					if($trx['id_product']==$promo_product->id_product){
						//set reference to this cart product
						$product=&$trx;
						// break from loop
						break;
					}
				}
				// product not found? buat jaga-jaga kalau sesuatu yang tidak diinginkan terjadi
				if(!$product){
					$errors[]='Cart doesn\'t contain promoted product';
					return false;
				}
				return true;
				break;

			case 'Buy X Get Y':
				// load requirement relationship
				$promo->load('promo_campaign_buyxgety_rules','promo_campaign_buyxgety_product_requirement');
				$promo_product=$promo->promo_campaign_buyxgety_product_requirement;
				if(!$promo_product){
					$errors[]='Benefit product is not set correctly';
					return false;
				}
				// promo product not available in cart?
				if(!in_array($promo_product->id_product, array_column($trxs, 'id_product'))){
					$errors[]='Requirement product doesnt available in cart';
					return false;
				}
				//get cart's product to get benefit
				$product=null;
				foreach ($trxs as &$trx) {
					//is this the cart product we looking for?
					if($trx['id_product']==$promo_product->id_product){
						//set reference to this cart product
						$product=&$trx;
						// break from loop
						break;
					}
				}
				// product not found? buat jaga-jaga kalau sesuatu yang tidak diinginkan terjadi
				if(!$product){
					$errors[]='Requirement product doesnt available in cart';
					return false;
				}
				return true;
				break;

			case 'Discount global':
				return true;
				break;
		}
	}

	/**
	 * modify $trx set discount to product
	 * @param  Product 								$product
	 * @param  PromoCampaignProductDiscountRule 	$promo_rules
	 * @param  Array 								$trx 			transaction data
	 * @return int discount
	 */
	protected function discount_product($product,$promo_rules,&$trx, $modifier=null){
		// check discount type
		$discount=0;
		// set quantity of product to apply discount
		$discount_qty=$trx['qty'];
		$old=$trx['discount']??0;
		// is there any max qty set?
		if(($promo_rules->max_qty??false)&&$promo_rules->max_qty<$discount_qty){
			$discount_qty=$promo_rules->max_qty;
		}

		// check 'product discount' limit product qty
		if(($promo_rules->max_product??false)&&$promo_rules->max_product<$discount_qty){
			$discount_qty=$promo_rules->max_product;
		}

		// check if isset promo qty
		if (isset($trx['promo_qty'])) {
			$discount_qty = $trx['promo_qty'];
			unset($trx['promo_qty']);
		}

		$product_price = ($product->product_price??$product->product_prices[0]->product_price??null) + $modifier;

		if(isset($trx['new_price'])&&$trx['new_price']){
			$product_price=$trx['new_price']/$trx['qty'];
		}
		if($promo_rules->discount_type=='Nominal' || $promo_rules->discount_type=='nominal'){
			$discount=$promo_rules->discount_value*$discount_qty;
			$trx['discount']=($trx['discount']??0)+$discount;
			$trx['new_price']=($product_price*$trx['qty'])-$trx['discount'];
			$trx['is_promo']=1;
		}else{
			// percent
			$discount_per_product = ($promo_rules->discount_value/100)*$product_price;
			if ($discount_per_product > $promo_rules->max_percent_discount && !empty($promo_rules->max_percent_discount)) {
				$discount_per_product = $promo_rules->max_percent_discount;
			}
			$discount=(int)($discount_per_product*$discount_qty);
			$trx['discount']=($trx['discount']??0)+$discount;
			$trx['new_price']=($product_price*$trx['qty'])-$trx['discount'];
			$trx['is_promo']=1;
		}
		if($trx['new_price']<0){
			$trx['is_promo']=1;
			$trx['new_price']=0;
			$trx['discount']=$product_price*$discount_qty;
			$discount=$trx['discount']-$old;
		}
		return $discount;
	}

	/**
	 * Validate if a user can use promo
	 * @param  int 		$id_promo id promo campaign
	 * @param  int 		$id_user  id user
	 * @return boolean	true/false
	 */
	public function validateUser($id_promo, $id_user, $phone, $device_type=null, $device_id, &$errors=[],$id_code=null){
		$promo=PromoCampaign::find($id_promo);

		if(!$promo){
        	$errors[]='Promo campaign not found';
    		return false;
		}
		if(!$promo->step_complete || !$promo->user_type){
        	$errors[]='Promo campaign not finished';
    		return false;
		}

		if($promo->promo_type == 'Referral'){
			if(User::find($id_user)->transaction_online){
	        	$errors[]='Promo code not valid';
				return false;
			}
			if(UserReferralCode::where([
				'id_promo_campaign_promo_code'=>$id_code,
				'id_user'=>$id_user
			])->exists()){
	        	$errors[]='Promo code not valid';
	    		return false;
			}
	        $referer = UserReferralCode::where('id_promo_campaign_promo_code',$id_code)
	            ->join('users','users.id','=','user_referral_codes.id_user')
	            ->where('users.is_suspended','=',0)
	            ->first();
	        if(!$referer){
	        	$errors[] = 'Promo code not valid';
	        }
		}

		//check user
		$user = $this->userFilter($id_user, $promo->user_type, $promo->specific_user, $phone);

        if(!$user){
        	$errors[]='User not found';
    		return false;
        }

        // use promo code?
        if($promo->limitation_usage){
        	// limit usage user?
        	if(PromoCampaignReport::where('id_promo_campaign',$id_promo)->where('id_user',$id_user)->count()>=$promo->limitation_usage){
	        	// $errors[]='Kuota anda untuk penggunaan kode promo ini telah habis';
	        	$errors[]='Your quota for using this promo code has been exceeded';
	    		return false;
        	}

        	// limit usage device
        	if(PromoCampaignReport::where('id_promo_campaign',$id_promo)->where('device_id',$device_id)->count()>=$promo->limitation_usage){
	        	// $errors[]='Kuota device anda untuk penggunaan kode promo ini telah habis';
	        	$errors[]='Your device quota for using this promo code has been exceeded';
	    		return false;
        	}
        }
        return true;
	}

	/**
	 * Get product price with product discount
	 * @param  Product $product product
	 * @return int          new product price
	 */
	public function getProductDiscount($product){
		$product->load('discountActive');
		$productItem=$product->toArray();
		$productItem['discountActive']=$productItem['discount_active'];
    	$countSemen=0;
        if (count($productItem['discountActive']) > 0) {
            $productItem['discount_status'] = 'yes';
        } else {
            $productItem['discount_status'] = 'no';
        }
        if ($productItem['discount_status'] == 'yes') {
            foreach ($productItem['discountActive'] as $row => $dis) {
                if (!empty($dis['discount_percentage'])) {
                    $jat = $dis['discount_percentage'];

                    $count = $productItem['product_prices'][0]['product_price']??[] * $jat / 100;
                } else {
                    $count = $dis['discount_nominal'];
                }

                $now = date('Y-m-d');
                $time = date('H:i:s');
                $day = date('l');

                if ($now < $dis['discount_start']) {
                    $count = 0;
                }

                if ($now > $dis['discount_end']) {
                    $count = 0;
                }

                if ($time < $dis['discount_time_start']) {
                    $count = 0;
                }

                if ($time > $dis['discount_time_end']) {
                    $count = 0;
                }

                if (strpos($dis['discount_days'], $day) === false) {
                    $count = 0;
                }

                $countSemen += $count;
                $count = 0;
            }
        }
        if($countSemen>$productItem['product_prices'][0]['product_price']??[]){
        	$countSemen=$productItem['product_prices'][0]['product_price']??[];
        }
        return $countSemen;
    }

    public function userFilter($id_user, $rule, $valid_user, $phone)
    {
    	if ($rule == 'New user')
    	{
    		$check = Transaction::where('id_user', '=', $id_user)->first();
    		if ($check) {
    			return false;
    		}
    	}
    	elseif ($rule == 'Specific user')
    	{
    		$valid_user = explode(',', $valid_user);
    		if (!in_array($phone, $valid_user)) {
    			return false;
    		}
    	}

    	return true;
    }

    function checkOutletRule($id_outlet, $rule, $outlet = null)
    {
        if ($rule == '1')
        {
            return true;
        }
        elseif ($rule == '0')
        {
            foreach ($outlet as $value)
            {
                if ( $value['id_outlet'] == $id_outlet )
                {
                    return true;
                }
            }

            return false;
        }
        else
        {
            return false;
        }
    }

    function getMessage($key)
    {
    	$message = Setting::where('key', '=', $key)->first()??null;

    	return $message;
    }

    function getRequiredProduct($id_promo, $source='promo_campaign'){
    	if ($source == 'deals') {
    		$promo = Deal::where('id_deals','=',$id_promo)
	    			->with([
						'deals_product_discount.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'deals_buyxgety_product_requirement.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'deals_tier_discount_product.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'deals_product_discount_rules',
						'deals_tier_discount_rules',
						'deals_buyxgety_rules'
					])
	                ->first();
    	}elseif($source == 'promo_campaign'){
	    	$promo = PromoCampaign::where('id_promo_campaign','=',$id_promo)
	    			->with([
						'promo_campaign_product_discount.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign_buyxgety_product_requirement.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign_tier_discount_product.product' => function($q) {
							$q->select('id_product', 'id_product_category', 'product_code', 'product_name');
						},
						'promo_campaign_product_discount_rules',
						'promo_campaign_tier_discount_rules',
						'promo_campaign_buyxgety_rules'
					])
	                ->first();
    	}

        if ($promo) {
        	$promo = $promo->toArray();

        	if ( ($promo[$source.'_product_discount_rules']['is_all_product']??false) == 1)
	        {
	        	$product = ['all' => '*'];
	        }
	        elseif ( !empty($promo[$source.'_product_discount']) )
	        {
	        	$total_product = count($promo[$source.'_product_discount']);
	        	if ($total_product == 1) {
	        		$product = $promo[$source.'_product_discount'][0]['product']['product_group']??$promo[$source.'_product_discount'][0]['product_group']??'';
	        	}else{
	        		$product = null;
	        	}
	        }
	        elseif ( !empty($promo[$source.'_tier_discount_product']) )
	        {
	        	$product = $promo[$source.'_tier_discount_product']['product']['product_group']??$promo[$source.'_tier_discount_product']['product_group']??'';
	        }
	        elseif ( !empty($promo[$source.'_buyxgety_product_requirement']) )
	        {
	        	$product = $promo[$source.'_buyxgety_product_requirement']['product']['product_group']??$promo[$source.'_buyxgety_product_requirement']['product_group']??'';
	        }
	        else
	        {
	        	$product = null;
	        }
	        return $product;
        }else{
        	return 'empty';
        }
    }

    function getAllModifier($array_modifier, $id_outlet)
    {
    	$mod = ProductModifier::select('product_modifiers.id_product_modifier','text','product_modifier_stock_status','product_modifier_price')
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
                ->whereIn('product_modifiers.id_product_modifier',$array_modifier)
                ->groupBy('product_modifiers.id_product_modifier')
                // product modifier dengan id
                ->get();
        if ($mod) {
        	return $mod;
        }else{
        	return [];
        }

    }

    public function getOneProduct($id_outlet, $id_product, $brand=null, $variant=null)
    {
    	$product = Product::join('product_prices','product_prices.id_product','=','products.id_product')
	                ->where('product_prices.id_outlet','=',$id_outlet)
	                ->where('products.id_product','=',$id_product)
	                ->where(function($query){
	                    $query->where('product_prices.product_visibility','=','Visible')
	                            ->orWhere(function($q){
	                                $q->whereNull('product_prices.product_visibility')
	                                ->where('products.product_visibility', 'Visible');
	                            });
	                })
	                ->where('product_prices.product_status','=','Active')
	                ->whereNotNull('product_prices.product_price');

		if (!empty($brand)) {

			$product = $product->with('brands');
		}

		if (!empty($variant)) {

			$product = $product->with('product_variants');
		}

		$product = $product->first();

		return $product;
    }

    public function getProductPrice($id_outlet, $id_product, $id_brand, $id_product_group)
    {
    	$query = Product::join('product_groups','products.id_product_group','=','product_groups.id_product_group')
                    ->where('product_groups.id_product_group',$id_product_group)
                    // join product_price (product_outlet pivot and product price data)
                    ->join('product_prices','product_prices.id_product','=','products.id_product')
                    ->where('product_prices.id_outlet','=',$id_outlet) // filter outlet
                    ->where('product_prices.product_stock_status','=','Available') // filter stock available
                    ->join('brand_product','brand_product.id_product','=','products.id_product')
                    ->where('brand_product.id_brand',$id_brand)
                    // brand produk ada di outlet
                    ->where('brand_outlet.id_outlet','=',$id_outlet)
                    ->join('brand_outlet','brand_outlet.id_brand','=','brand_product.id_brand')
                    // where active
                    ->where(function($query){
                        $query->where('product_prices.product_visibility','=','Visible')
                                ->orWhere(function($q){
                                    $q->whereNull('product_prices.product_visibility')
                                    ->where('products.product_visibility', 'Visible');
                                });
                    })
                    ->where('product_prices.product_status','=','Active')
                    ->whereNotNull('product_prices.product_price')
                    ->where('products.id_product','=',$id_product)
                    ->first();

        return $query;
    }
    /**
     * Create referal promo code
     * @param  Integer $id_user user id of user
     * @return boolean       true if success
     */
    public static function createReferralCode($id_user) {
    	//check user have referral code
    	$referral_campaign = PromoCampaign::select('id_promo_campaign')->where('promo_type','referral')->first();
    	if(!$referral_campaign){
    		return false;
    	}
    	$check = UserReferralCode::where('id_user',$id_user)->first();
    	if($check){
    		return $check;
    	}
    	$max_iterate = 1000;
    	$iterate = 0;
    	$exist = true;
    	do{
    		$promo_code = MyHelper::createrandom(6, 'PromoCode');
    		$exist = PromoCampaignPromoCode::where('promo_code',$promo_code)->exists();
    		if($exist){$promo_code=false;};
    		$iterate++;
    	}while($exist&&$iterate<=$max_iterate);
    	if(!$promo_code){
    		return false;
    	}
    	$create = PromoCampaignPromoCode::create([
    		'id_promo_campaign' => $referral_campaign->id_promo_campaign,
    		'promo_code' => $promo_code
    	]);
    	if(!$create){
    		return false;
    	}
    	$create2 = UserReferralCode::create([
    		'id_promo_campaign_promo_code' => $create->id_promo_campaign_promo_code,
    		'id_user' => $id_user
    	]);
    	return $create2;
    }
    /**
     * Apply cashback to referrer
     * @param  Transaction $transaction Transaction model
     * @return boolean
     */
    public static function applyReferrerCashback($transaction)
    {
    	if(!$transaction['id_promo_campaign_promo_code']){
    		return true;
    	}
    	$transaction->load('promo_campaign_promo_code','promo_campaign_promo_code.promo_campaign');
    	$use_referral = ($transaction['promo_campaign_promo_code']['promo_campaign']['promo_type']??false) === 'Referral';
        // apply cashback to referrer
        if ($use_referral){
            $referral_rule = PromoCampaignReferral::where('id_promo_campaign',$transaction['promo_campaign_promo_code']['id_promo_campaign'])->first();
            $referral_code = UserReferralCode::where('id_promo_campaign_promo_code',$transaction['id_promo_campaign_promo_code'])->first();
            if(!$referral_code || !$referral_rule){
            	return false;
            }
            $referrer = $referral_code->id_user;
            $referrer_cashback = 0;
            if($referral_rule->referrer_promo_unit == 'Percent'){
                $referrer_discount_percent = $referral_rule->referrer_promo_value<=100?$referral_rule->referrer_promo_value:100;
                $referrer_cashback = $transaction['transaction_grandtotal']*$referrer_discount_percent/100;
            }else{
                if($transaction['transaction_grandtotal'] >= $referral_rule->referred_min_value){
                    $referrer_cashback = $referral_rule->referrer_promo_value<=$transaction['transaction_grandtotal']?$referral_rule->referrer_promo_value:$transaction['transaction_grandtotal'];
                }
            }
            if($referrer_cashback){
                $insertDataLogCash = app("Modules\Balance\Http\Controllers\BalanceController")->addLogBalance( $referrer, $referrer_cashback, $transaction['id_transaction'], 'Referral Bonus', $transaction['transaction_grandtotal']);
                if (!$insertDataLogCash) {
                    return false;
                }
                $up = PromoCampaignReferralTransaction::where('id_transaction',$transaction['id_transaction'])->update(['referrer_bonus'=>$referrer_cashback]);
	            if(!$up){
	            	return false;
	            }
            }
            $referral_code->refreshSummary();
        }
        return true;
    }

    public function removeBonusItem($item)
    {
    	foreach ($item as $key => $value)
		{
			if (!empty($value['bonus'])) {
				unset($item[$key]);
				break;
			}
		}

		return $item;
    }

    function getProductName($product_group, $variants=[]){
    	$name = $product_group['product_group_name'];

    	foreach ($variants as $key => $variant) {
    		if ($variant['product_variant_code'] == 'general_type' || $variant['product_variant_code'] == 'general_size') {
    			continue;
    		}
    		$name .= ' '.$variant['product_variant_name'];
    	}
    	
    	return $name;
    }

    public function checkShipmentRule($all_shipment, $shipment_method, $promo_shipment_list)
    {
    	if (!is_array($promo_shipment_list)) {
    		$promo_shipment_list = $promo_shipment_list->toArray();
    	}

    	if ($all_shipment) {
    		return true;
    	}

    	if (in_array($shipment_method, $promo_shipment_list)) {
    		return true;
    	}else{
    		return false;
    	}
    }

    public function discountDelivery($delivery_fee, $discount_type, $discount_value, $discount_max)
    {
    	$discount = 0;
    	if($discount_type == 'Percent'){
			$discount = ($delivery_fee * $discount_value)/100;
			if(!empty($discount_max) && $discount > $discount_max){
				$discount = $discount_max;
			}
		}else{
			if($discount_value < $delivery_fee){
				$discount = $discount_value;
			}else{
				$discount = $delivery_fee;
			}
		}

		return $discount;
    }

    public function validateDelivery($request, $result, &$promo_delivery_error)
    {
    	if (!$request->promo_code_delivery && !$request->id_deals_user_delivery) {
    		return null;
    	}

    	$stop_trx = true;
    	$promo_error = null;
    	$post = $request->json()->all();
    	$promo_delivery = null;
    	$min_basket_size = null;

    	if($request->promo_code_delivery)
        {
        	$code = app($this->promo_campaign)->checkPromoCode($request->promo_code_delivery, 1, 1);
            if ($code)
            {
	        	if ($code['promo_campaign']['date_end'] < date('Y-m-d H:i:s')) {
	        		$error = ['Promo campaign is ended'];
            		$promo_error = $error;
	        	}
	        	else
	        	{
		            $validate_user = $this->validateUser($code->id_promo_campaign, $request->user()->id, $request->user()->phone, $request->device_type, $request->device_id, $errore,$code->id_promo_campaign_promo_code);

		            if ($validate_user) {
			            $discount_promo = $this->validatePromo($code->id_promo_campaign, $request->id_outlet, $post['item'], $errors, 'promo_campaign', $post['payment_type'] ?? null, $error_product, $request, $result['shipping']);

			            if ($discount_promo) {
			            	$promo = $code->promo_campaign;
			            	$promo_shipment = $code->promo_campaign->promo_campaign_shipment_method->pluck('shipment_method');
				            $min_basket_size = $code->min_basket_size;
				            $promo_value = (string) MyHelper::requestNumber(($result['shipping'] - $discount_promo['discount_delivery']),'_CURRENCY');
				            $promo_value_int = ($result['shipping'] - $discount_promo['discount_delivery']);
				            $shipping_value = (string) MyHelper::requestNumber($result['shipping'],'_CURRENCY');
				            $promo_delivery = [
					            'title' 		=> $code->campaign_name,
				            	'description'	=> $discount_promo['new_description'],
					            'detail' 		=> $discount_promo['promo_detail'],
					            'value' 		=> $promo_value,
					            'value_int' 	=> $promo_value_int,
					            'discount_delivery' => $discount_promo['discount_delivery'],
					            'is_free' 		=> $discount_promo['is_free'],
					            'shipping_value'=> $shipping_value,
					            'type' 			=> 'discount_delivery',
					            'id_promo_code' => $code->id_promo_campaign_promo_code,
					            'id_promo_campaign' => $code->id_promo_campaign
				            ];
			            }else{
			            	$promo_error = $errors;
			            }

		            }else{
		            	if(!empty($errore)){
		            		$promo_error = $errore;
		            	}
		            }
	        	}
            }
            else
            {
            	$error = ['Promo code invalid'];
            	$promo_error = $error;
            }
        }
        elseif($request->id_deals_user_delivery && is_numeric($request->id_deals_user_delivery))
        {
	        $deals = DealsUser::whereIn('paid_status', ['Free', 'Completed'])
	        		->with('dealVoucher.deal')
	        		->where('id_deals_user', $request->id_deals_user_delivery)
	        		->first();

	        if (!$deals){
	        	$error = ['Voucher is not found'];
	        	$promo_error = $error;
	        }elseif( !empty($deals['used_at']) ){
	        	$error = ['Voucher already used'];
	        	$promo_error = $error;
	        }elseif( date('Y-m-d H:i:s', strtotime($deals['voucher_expired_at'])) < date('Y-m-d H:i:s') ){
	        	$error = ['Voucher is expired'];
	        	$promo_error = $error;
	        }elseif( !empty($deals['voucher_active_at']) && date('Y-m-d H:i:s', strtotime($deals['voucher_active_at'])) > date('Y-m-d H:i:s') ){
	        	$error = ['Voucher periode hasn\'t started'];
	        	$promo_error = $error;
	        }elseif($deals){
				$validate_user = true;
				$promo = $deals->dealVoucher->deals;
				$promo_shipment = $deals->dealVoucher->deals->deals_shipment_method->pluck('shipment_method');
				
				$discount_promo=$this->validatePromo($deals->dealVoucher->id_deals, $request->id_outlet, $post['item'], $errors, 'deals', null, $error_product, $request, $result['shipping']);

				if ($discount_promo) {
		            $min_basket_size = $deals->dealVoucher->deal->min_basket_size;
		            $promo_value = (string) MyHelper::requestNumber(($result['shipping'] - $discount_promo['discount_delivery']),'_CURRENCY');
		            $promo_value_int = ($result['shipping'] - $discount_promo['discount_delivery']);
				    $shipping_value = (string) MyHelper::requestNumber($result['shipping'],'_CURRENCY');
		            $promo_delivery = [
		            	'title' 		=> $deals->dealVoucher->deals->deals_title,
		            	'description'	=> $discount_promo['new_description'],
			            'detail' 		=> $discount_promo['promo_detail'],
			            'value' 		=> $promo_value,
			            'value_int' 	=> $promo_value_int,
			            'discount_delivery' => $discount_promo['discount_delivery'],
			            'is_free' 		=> $discount_promo['is_free'],
			            'shipping_value'=> $shipping_value,
			            'type' 			=> 'discount_delivery',
			            'id_deals_voucher' => $deals->id_deals_voucher
		            ];
	            }else{
	            	$promo_error = $errors;
	            }
	        }
	        else
	        {
	        	$error = ['Voucher is not valid'];
	        	$promo_error = $error;
	        }
        }else{
        	$promo_error = ['Promo is invalid'];
        }

        // check minimum basket size
        if (!$promo_error) {
        	$subtotal = $result['subtotal'] - abs($result['discount'] ?? 0);
	        if ($min_basket_size > $subtotal) {
    			$promo_error = ['your total order is less than '.number_format($min_basket_size,0,',','.')];
    			$promo_delivery = null;
    			$stop_trx = false;
	        }
	    }

	    // check available shipment
        if (!$promo_error) {
	        if ( !empty($result['allow_delivery']) ) {

        		$available_delivery = [];
	        	$availableDelivery = config('delivery_method');

	        	$shipment_list = $this->getAvailableShipment($request->id_outlet);

		        $setting  = json_decode(MyHelper::setting('active_delivery_methods', 'value_text', '[]'), true) ?? [];
		        $deliveries = [];

		        foreach ($shipment_list as $value) {
			    	if ($this->checkShipmentRule($promo->is_all_shipment, $value['code'], $promo_shipment)) {
			    		$available_delivery[] = $value['code'];
			    	}
		        }

		        $promo_delivery['available_delivery'] = $available_delivery;
		    	if(empty($available_delivery)) {
		    		$promo_delivery['allow_delivery'] = 0;
		    	}else{
		    		$promo_delivery['allow_pickup'] = 0;
		    		$promo_delivery['allow_delivery'] = 1;
		    	}
	    	}
        }

        if ($promo_error) {
        	$promo_delivery_error = [
        		'title' => 'Delivery Promo Could Not Be Applied',
        		'subtitle' => '',
        		'messages' => $promo_error,
        		'stop_trx' => $stop_trx
        	];
        }

        return $promo_delivery;
    }

    public function getAvailableShipment($id_outlet)
    {
		$data = null;
    	$custom_data 	= [
    		'id_outlet' => $id_outlet
    	];
    	$custom_request = new \Illuminate\Http\Request;
		$custom_request = $custom_request
						->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($custom_data))
						->merge($custom_data);

		$shipment_list 	= app($this->online_transaction)->availableShipment($custom_request);

		if(($shipment_list['status']??false) == 'success'){
			$temp = $shipment_list['result'];
			$data = [];
			foreach ($shipment_list['result'] as $key => $value) {
				if (!isset($value['code'])) {
					continue;
				}
				$data[$value['code']] = $value;
			}

		}

		return $data;
    }
}
?>
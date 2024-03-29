<?php

/**
 * Created by Reliese Model.
 * Date: Mon, 16 Dec 2019 16:41:26 +0700.
 */

namespace Modules\PromoCampaign\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;
use Wildside\Userstamps\Userstamps;
/**
 * Class PromoCampaignTierDiscountProduct
 * 
 * @property int $id_promo_campaign_product_discount_rule
 * @property int $id_promo_campaign
 * @property int $id_product
 * @property int $id_product_category
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property \Modules\PromoCampaign\Entities\Product $product
 * @property \Modules\PromoCampaign\Entities\ProductCategory $product_category
 * @property \Modules\PromoCampaign\Entities\PromoCampaign $promo_campaign
 *
 * @package Modules\PromoCampaign\Entities
 */
class PromoCampaignTierDiscountProduct extends Eloquent
{
	use Userstamps;
	protected $primaryKey = 'id_promo_campaign_product_discount_rule';

	protected $appends  = [
		'get_product'
	];

	protected $casts = [
		'id_promo_campaign' => 'int',
		'id_product' => 'int',
		'id_product_category' => 'int'
	];

	protected $fillable = [
		'id_promo_campaign',
		'id_product',
		'id_product_category',
		'product_type'
	];

	public function product()
	{
		return $this->belongsTo(\App\Http\Models\Product::class, 'id_product');
	}

	public function product_category()
	{
		return $this->belongsTo(\App\Http\Models\ProductCategory::class, 'id_product_category');
	}

	public function promo_campaign()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaign::class, 'id_promo_campaign');
	}

	public function product_group()
	{
		return $this->hasOne(\Modules\ProductVariant\Entities\ProductGroup::class, 'id_product_group', 'id_product');
	}

	public function getGetProductAttribute() {

        if( $this->product_type == 'group')
        {
        	$this->load(['product_group' => function($q){
        		$q->select('id_product_group', 'product_group_code', 'product_group_name', 'id_product_category');
        	}]);
        } 
        else
        {
        	$this->load(['product.product_group' => function($q){
        		$q->select('id_product_group', 'product_group_code', 'product_group_name', 'id_product_category');
        	}]);
        }
    }
}

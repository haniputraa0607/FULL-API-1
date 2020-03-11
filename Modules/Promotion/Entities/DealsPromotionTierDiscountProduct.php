<?php

/**
 * Created by Reliese Model.
 * Date: Wed, 04 Mar 2020 16:17:49 +0700.
 */

namespace Modules\Promotion\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class DealsPromotionTierDiscountProduct
 * 
 * @property int $id_deals_tier_discount_products
 * @property int $id_deals
 * @property string $product_type
 * @property int $id_product
 * @property int $id_product_category
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property \App\Models\DealsPromotionTemplate $deals_promotion_template
 * @property \App\Models\ProductCategory $product_category
 *
 * @package App\Models
 */
class DealsPromotionTierDiscountProduct extends Eloquent
{
	protected $primaryKey = 'id_deals_tier_discount_products';

	protected $casts = [
		'id_deals' => 'int',
		'id_product' => 'int',
		'id_product_category' => 'int'
	];

	protected $fillable = [
		'id_deals',
		'product_type',
		'id_product',
		'id_product_category'
	];

	public function deals_promotion_template()
	{
		return $this->belongsTo(\App\Http\Models\DealsPromotionTemplate::class, 'id_deals');
	}

	public function product_category()
	{
		return $this->belongsTo(\App\Http\Models\ProductCategory::class, 'id_product_category');
	}
}

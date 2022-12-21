<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;

class DealsProductCategoryRule extends Model
{
    protected $table = 'deals_productcategory_rules';
	protected $primaryKey = 'id_deals_productcategory_rules';

	protected $casts = [
		'id_deals' => 'int',
		'min_qty_requirement' => 'int',
		'benefit_qty' => 'int',
		'max_percent_discount' => 'int'
	];

	protected $fillable = [
		'id_deals',
		'min_qty_requirement',
		'benefit_qty',
		'discount_type',
		'discount_value',
		'max_percent_discount'
	];

	public function deals()
	{
		return $this->belongsTo(\Modules\Deals\Entities\Deals::class, 'id_deals');
	}

	public function product_category()
	{
		return $this->belongsTo(\App\Http\Models\ProductCategory::class, 'id_product_category');
	}
}

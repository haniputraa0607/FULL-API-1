<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductVariantDealsProductCategoryProductRequirement extends Model
{
    protected $table = 'product_variant_deals_productcategory_category_requirements';
	protected $primaryKey = 'id_product_variant_deals_productcategory_category_requirement';

	protected $appends  = [
		'get_product_variant',
	];

	protected $casts = [
		'id_deals_productcategory_category_requirement' => 'int',
		'id_product_variant' => 'int'
	];

	protected $fillable = [
		'id_deals_productcategory_category_requirement',
		'id_product_variant',
		'created_at',
		'updated_at'
	];

	public function product_variant()
	{
		return $this->belongsTo(\Modules\ProductVariant\Entities\ProductVariant::class, 'id_product_variant');
	}

    public function productvariant_category_requirements()
	{
		return $this->belongsTo(\Modules\Deals\Entities\DealsProductCategoryProductRequirement::class, 'id_deals_productcategory_category_requirement');
	}

	public function getGetProductVariantAttribute() {
		
		$this->load(['product_variant']);
    }
}

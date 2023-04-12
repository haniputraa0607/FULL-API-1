<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;

class DealsProductCategoryProductRequirement extends Model
{
    protected $table = 'deals_productcategory_category_requirements';
	protected $primaryKey = 'id_deals_productcategory_category_requirement';

	protected $appends  = [
		'get_product_variant',
		'get_product_category'
	];

	protected $casts = [
		'id_deals' => 'int',
	];

	protected $fillable = [
		'id_deals',
		'product_type',
		'auto_apply'
	];

	public function product_category()
	{
		return $this->hasMany(\Modules\Deals\Entities\CategoryDealsProductCategoryProductRequirement::class, 'id_deals_productcategory_category_requirement');
	}

	public function product_variant()
	{
		return $this->hasMany(\Modules\Deals\Entities\ProductVariantDealsProductCategoryProductRequirement::class, 'id_deals_productcategory_category_requirement');
	}

	public function deals()
	{
		return $this->belongsTo(\Modules\Deals\Entities\Deals::class, 'id_deals');
	}

	public function getGetProductVariantAttribute() {
		
		$this->load(['product_variant']);
    }

	public function getGetProductCategoryAttribute() {
		
		$this->load(['product_category']);
    }
}

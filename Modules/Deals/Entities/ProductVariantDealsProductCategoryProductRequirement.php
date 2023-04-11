<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductVariantDealsProductCategoryProductRequirement extends Model
{
    protected $table = 'product_variant_deals_productcategory_category_requirements';
	protected $primaryKey = 'id_product_variant_deals_productcategory_category_requirement';

	protected $appends  = [
		'get_variant_size',
		'get_variant_type',
	];

	protected $casts = [
		'id_deals_productcategory_category_requirement' => 'int',
		'size' => 'int',
		'type' => 'int'
	];

	protected $fillable = [
		'id_deals_productcategory_category_requirement',
		'size',
		'type',
		'created_at',
		'updated_at'
	];

	public function variant_size()
	{
		return $this->belongsTo(\Modules\ProductVariant\Entities\ProductVariant::class, 'size');
	}

	public function variant_type()
	{
		return $this->belongsTo(\Modules\ProductVariant\Entities\ProductVariant::class, 'type');
	}

    public function productvariant_category_requirements()
	{
		return $this->belongsTo(\Modules\Deals\Entities\DealsProductCategoryProductRequirement::class, 'id_deals_productcategory_category_requirement');
	}

	public function getGetVariantSizeAttribute() {
		
		$this->load(['variant_size']);
    }

	public function getGetVariantTypeAttribute() {
		
		$this->load(['variant_type']);
    }
}

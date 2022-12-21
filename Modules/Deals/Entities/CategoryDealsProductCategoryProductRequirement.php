<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;

class CategoryDealsProductCategoryProductRequirement extends Model
{
    protected $table = 'category_deals_productcategory_category_requirements';
	protected $primaryKey = 'id_category_deals_productcategory_category_requirement';

	protected $appends  = [
		'get_product_category',
	];

	protected $casts = [
		'id_deals_productcategory_category_requirement' => 'int',
		'id_product_category' => 'int'
	];

	protected $fillable = [
		'id_deals_productcategory_category_requirement',
		'id_product_category',
		'created_at',
		'updated_at'
	];

	public function product_category()
	{
		return $this->belongsTo(\App\Http\Models\ProductCategory::class, 'id_product_category');
	}

    public function productcategory_category_requirements()
	{
		return $this->belongsTo(\Modules\Deals\Entities\DealsProductCategoryProductRequirement::class, 'id_deals_productcategory_category_requirement');
	}

	public function getGetProductCategoryAttribute() {
		
		$this->load(['product_category']);
    }
}

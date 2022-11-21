<?php

namespace Modules\PromoCampaign\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class CategoryPromoCampaignProductCategoryProductRequirement extends Model
{
    use Userstamps;
	protected $table = 'category_promo_campaign_productcategory_category_requirements';
	protected $primaryKey = 'id_category_promo_campaign_productcategory_category_requirement';

	protected $appends  = [
		'get_product_category',
	];

	protected $casts = [
		'id_promo_campaign_productcategory_category_requirement' => 'int',
		'id_product_category' => 'int'
	];

	protected $fillable = [
		'id_promo_campaign_productcategory_category_requirement',
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
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaignProductCategoryProductRequirement::class, 'id_promo_campaign_productcategory_category_requirement');
	}

	public function getGetProductCategoryAttribute() {
		
		$this->load(['product_category']);
    }
}

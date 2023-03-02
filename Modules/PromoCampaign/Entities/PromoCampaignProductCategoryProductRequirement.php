<?php

namespace Modules\PromoCampaign\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;
use Wildside\Userstamps\Userstamps;

class PromoCampaignProductCategoryProductRequirement extends Eloquent
{
    use Userstamps;
	protected $table = 'promo_campaign_productcategory_category_requirements';
	protected $primaryKey = 'id_promo_campaign_productcategory_category_requirement';

	protected $appends  = [
		'get_product_variant',
		'get_product_category'
	];

	protected $casts = [
		'id_promo_campaign' => 'int',
		'id_product_variant' => 'int'
	];

	protected $fillable = [
		'id_promo_campaign',
		'id_product_variant',
		'product_type',
		'auto_apply'
	];

	public function product_variant()
	{
		return $this->hasMany(\Modules\PromoCampaign\Entities\VariantPromoCampaignProductCategoryProductRequirement::class, 'id_promo_campaign_productcategory_category_requirement');
	}

	public function product_category()
	{
		return $this->hasMany(\Modules\PromoCampaign\Entities\CategoryPromoCampaignProductCategoryProductRequirement::class, 'id_promo_campaign_productcategory_category_requirement');
	}

	public function promo_campaign()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaign::class, 'id_promo_campaign');
	}

	public function getGetProductVariantAttribute() {
		
		$this->load(['product_variant']);
    }

	public function getGetProductCategoryAttribute() {
		
		$this->load(['product_category']);
    }
}

<?php

namespace Modules\PromoCampaign\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;
use Wildside\Userstamps\Userstamps;

class PromoCampaignProductCategoryProductRequirement extends Eloquent
{
    use Userstamps;
	protected $table = 'promo_campaign_productcategory_category_requirements';
	protected $primaryKey = 'id_promo_campaign_productcategory_category_requirement';

	protected $casts = [
		'id_promo_campaign' => 'int',
		'id_product_category' => 'int'
	];

	protected $fillable = [
		'id_promo_campaign',
		'id_product_category',
		'product_type'
	];

	public function product_category()
	{
		return $this->belongsTo(\App\Http\Models\ProductCategory::class, 'id_product_category');
	}

	public function promo_campaign()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaign::class, 'id_promo_campaign');
	}

}

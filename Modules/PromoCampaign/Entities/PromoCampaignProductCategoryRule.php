<?php

namespace Modules\PromoCampaign\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;
use Wildside\Userstamps\Userstamps;

class PromoCampaignProductCategoryRule extends Eloquent
{
    use Userstamps;
	protected $table = 'promo_campaign_productcategory_rules';
	protected $primaryKey = 'id_promo_campaign_productcategory_rules';

	protected $casts = [
		'id_promo_campaign' => 'int',
		'min_qty_requirement' => 'int',
		'benefit_qty' => 'int',
		'max_percent_discount' => 'int'
	];

	protected $fillable = [
		'id_promo_campaign',
		'min_qty_requirement',
		'benefit_qty',
		'discount_type',
		'discount_value',
		'max_percent_discount'
	];

	public function promo_campaign()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaign::class, 'id_promo_campaign');
	}
}

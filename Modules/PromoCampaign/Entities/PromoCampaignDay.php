<?php

namespace Modules\PromoCampaign\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;
use Wildside\Userstamps\Userstamps;

class PromoCampaignDay extends Eloquent
{
    use Userstamps;
	protected $table = 'promo_campaign_days';
	protected $primaryKey = 'id_promo_campaign_day';

	protected $casts = [
		'id_promo_campaign' => 'int',
	];

	protected $fillable = [
		'id_promo_campaign',
		'day'
	];

	public function promo_campaign()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaign::class, 'id_promo_campaign');
	}
}

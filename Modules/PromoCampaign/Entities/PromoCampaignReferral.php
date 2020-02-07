<?php

namespace Modules\PromoCampaign\Entities;

use Illuminate\Database\Eloquent\Model;

class PromoCampaignReferral extends Model
{
    protected $primaryKey = 'id_promo_campaign_referrals';
    protected $fillable = [];
    public function promo_campaign() {
    	return $this->belongsTo(PromoCampaign::class,'id_promo_campaign','id_promo_campaign');
    }
}

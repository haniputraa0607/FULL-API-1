<?php

namespace Modules\PromoCampaign\Entities;

use Illuminate\Database\Eloquent\Model;

class PromoCampaignReferralTransaction extends Model
{
    protected $fillable = [
    	'id_promo_campaign_promo_code',
    	'id_user',
    	'id_transaction'
    ];
}
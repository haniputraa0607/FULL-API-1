<?php

namespace Modules\PromoCampaign\Entities;

use Illuminate\Database\Eloquent\Model;

class UserReferralCode extends Model
{
	public $primaryKey = 'id_user';
    protected $fillable = [
    	'id_promo_campaign_promo_code',
    	'id_user',
    	'number_transaction',
    	'cashback_earned'
    ];
  
    public function promo_code()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaignPromoCode::class, 'id_promo_campaign_promo_code', 'id_promo_campaign_promo_code');
	}

    public function refreshSummary()
    {
        \Log::debug($this->attributes);
        $transaction = PromoCampaignReferralTransaction::select('id_promo_campaign_referral_transaction', 'promo_campaign_referral_transactions.id_transaction', 'referrer_bonus')->join('transactions', function($join) {
            $join->on('transactions.id_promo_campaign_promo_code', 'promo_campaign_referral_transactions.id_promo_campaign_promo_code')
                ->whereColumn('transactions.id_user', 'promo_campaign_referral_transactions.id_user');
        })
        ->join('transaction_pickups', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
        ->where('transaction_payment_status', 'Completed')
        ->where(function($q) {
            $q->whereNotNull('transaction_pickups.ready_at')->OrWhereNotNull('transaction_pickups.taken_by_system_at');
        })
        ->whereNull('transaction_pickups.reject_at')
        ->where('promo_campaign_referral_transactions.id_promo_campaign_promo_code', $this->id_promo_campaign_promo_code)
        ->distinct()
        ->get()->toArray();
        $this->update([
            'number_transaction' => count($transaction),
            'cashback_earned' => array_sum(array_column($transaction, 'referrer_bonus'))
        ]);
    }
}

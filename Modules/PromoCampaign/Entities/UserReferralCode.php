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
        $transaction = PromoCampaignReferralTransaction::select(\DB::raw('count(*) as total_trx, sum(referrer_bonus) as total_bonus'))
        ->join('transactions', function($join) {
            $join->on('transactions.id_promo_campaign_promo_code', 'promo_campaign_referral_transactions.id_promo_campaign_promo_code')
                ->whereColumn('transactions.id_transaction', 'promo_campaign_referral_transactions.id_transaction');
        })
        ->join('transaction_pickups', 'transactions.id_transaction', 'transaction_pickups.id_transaction')
        ->where('transaction_payment_status', 'Completed')
        ->where(function($q) {
            $q->whereNotNull('transaction_pickups.ready_at')->OrWhereNotNull('transaction_pickups.taken_by_system_at');
        })
        ->whereNull('transaction_pickups.reject_at')
        ->where('promo_campaign_referral_transactions.id_promo_campaign_promo_code', $this->id_promo_campaign_promo_code)
        ->first();
        $this->update([
            'number_transaction' => $transaction->total_trx,
            'cashback_earned' => $transaction->total_bonus
        ]);
    }
}

<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:18 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Transaction\Entities\TransactionPickupOutlet;
use App\Lib\MyHelper;
use App\Lib\Ovo;
use App\Lib\Midtrans;
use DB;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;

/**
 * Class Transaction
 *
 * @property int $id_transaction
 * @property int $id_user
 * @property string $transaction_receipt_number
 * @property string $transaction_notes
 * @property int $transaction_subtotal
 * @property int $transaction_shipment
 * @property int $transaction_service
 * @property int $transaction_discount
 * @property int $transaction_tax
 * @property int $transaction_grandtotal
 * @property int $transaction_point_earned
 * @property int $transaction_cashback_earned
 * @property string $transaction_payment_status
 * @property \Carbon\Carbon $void_date
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \App\Http\Models\User $user
 * @property \Illuminate\Database\Eloquent\Collection $transaction_payment_manuals
 * @property \Illuminate\Database\Eloquent\Collection $transaction_payment_midtrans
 * @property \Illuminate\Database\Eloquent\Collection $transaction_payment_offlines
 * @property \Illuminate\Database\Eloquent\Collection $products
 * @property \Illuminate\Database\Eloquent\Collection $transaction_shipments
 *
 * @package App\Models
 */
class Transaction extends Model
{
	protected $primaryKey = 'id_transaction';

	protected $casts = [
		'id_user' => 'int',
		// 'transaction_subtotal' => 'int',
		'transaction_shipment' => 'double',
		// 'transaction_service' => 'int',
		'transaction_discount' => 'double',
		// 'transaction_tax' => 'int',
		'transaction_grandtotal' => 'double',
		'transaction_point_earned' => 'int',
		'transaction_cashback_earned' => 'double'
	];

	protected $dates = [
		'void_date'
	];

	protected $fillable = [
		'id_user',
		'id_outlet',
		'id_promo_campaign_promo_code',
		'transaction_receipt_number',
		'transaction_notes',
		'transaction_subtotal',
		'transaction_shipment',
		'transaction_shipment_go_send',
		'transaction_is_free',
		'transaction_service',
		'transaction_discount',
		'transaction_tax',
		'trasaction_type',
		'transaction_cashier',
		'sales_type',
		'transaction_device_type',
		'transaction_grandtotal',
		'transaction_point_earned',
		'transaction_cashback_earned',
		'transaction_payment_status',
		'trasaction_payment_type',
		'void_date',
		'transaction_date',
		'special_memberships',
		'membership_level',
		'id_deals_voucher',
		'latitude',
		'longitude',
		'membership_promo_id',
		'completed_at',
		'show_rate_popup',
		'latest_reversal_process',
		'transaction_discount_delivery',
		'id_promo_campaign_promo_code_delivery',
		'transaction_shipping_method',
	];

	protected $balance = 'Modules\Balance\Http\Controllers\BalanceController';
	protected $autocrm = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
    protected $voucher  = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
    protected $promo_campaign = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
	protected $shopeepay      = "Modules\ShopeePay\Http\Controllers\ShopeePayController";

	public function user()
	{
		return $this->belongsTo(\App\Http\Models\User::class, 'id_user');
	}

	public function outlet()
	{
		return $this->belongsTo(\App\Http\Models\Outlet::class, 'id_outlet');
	}

	public function outlet_name()
	{
		return $this->belongsTo(\App\Http\Models\Outlet::class, 'id_outlet')->select('id_outlet', 'outlet_name');
	}

	public function transaction_payment_manuals()
	{
		return $this->hasMany(\App\Http\Models\TransactionPaymentManual::class, 'id_transaction');
	}

	public function transaction_payment_midtrans()
	{
		return $this->hasMany(\App\Http\Models\TransactionPaymentMidtran::class, 'id_transaction');
	}

	public function transaction_payment_offlines()
	{
		return $this->hasMany(\App\Http\Models\TransactionPaymentOffline::class, 'id_transaction');
	}
	public function transaction_payment_ovo()
	{
		return $this->hasMany(\App\Http\Models\TransactionPaymentOvo::class, 'id_transaction');
	}

	public function transaction_payment_ipay88()
	{
		return $this->hasOne(TransactionPaymentIpay88::class, 'id_transaction');
	}

	public function products()
	{
		return $this->belongsToMany(\App\Http\Models\Product::class, 'transaction_products', 'id_transaction', 'id_product')
					->select('product_categories.*','products.*')
					->leftJoin('product_categories', 'product_categories.id_product_category', '=', 'products.id_product_category')
					->withPivot('id_transaction_product', 'transaction_product_qty', 'transaction_product_price', 'transaction_product_price_base', 'transaction_product_price_tax', 'transaction_product_subtotal', 'transaction_modifier_subtotal', 'transaction_product_discount', 'transaction_product_note')
					->withTimestamps();
	}

    public function product_group()
    {
        return $this->belongsToMany(\App\Http\Models\Product::class, 'transaction_products', 'id_transaction', 'id_product')
            ->select('transaction_products.*','product_groups.product_group_name', 'products.product_name')->leftJoin('product_groups', 'product_groups.id_product_group', '=', 'products.id_product_group')
            ->withPivot('id_transaction_product', 'transaction_product_qty', 'transaction_product_price', 'transaction_product_price_base', 'transaction_product_price_tax', 'transaction_product_subtotal', 'transaction_modifier_subtotal', 'transaction_product_discount', 'transaction_product_note')
            ->withTimestamps();
    }

    public function products_variant()
    {
        return $this->belongsToMany(\App\Http\Models\Product::class, 'transaction_products', 'id_transaction', 'id_product')
            ->select('product_variants.*')
            ->join('product_product_variants', 'product_product_variants.id_product', '=', 'products.id_product')
            ->join('product_variants', 'product_variants.id_product_variant', '=', 'product_product_variants.id_product_variant');
    }


    public function modifiers()
	{
		return $this->hasMany(\App\Http\Models\TransactionProductModifier::class,'id_transaction');
	}

	public function transaction_shipments()
	{
		return $this->belongsTo(\App\Http\Models\TransactionShipment::class, 'id_transaction', 'id_transaction');
	}

    public function productTransaction()
    {
    	return $this->hasMany(TransactionProduct::class, 'id_transaction', 'id_transaction');
	}

    public function product_detail()
    {
    	if ($this->trasaction_type == 'Delivery') {
    		return $this->belongsTo(TransactionShipment::class, 'id_transaction', 'id_transaction');
    	} else {
    		return $this->belongsTo(TransactionPickup::class, 'id_transaction', 'id_transaction');
    	}
	}

    public function transaction_pickup()
    {
		return $this->belongsTo(TransactionPickup::class, 'id_transaction', 'id_transaction');
    }

    public function transaction_pickup_go_send()
    {
    	// make sure you have joined transaction_pickups before using this
		return $this->belongsTo(TransactionPickupGoSend::class, 'id_transaction_pickup', 'id_transaction_pickup');
    }

    public function transaction_pickup_outlet()
    {
    	// make sure you have joined transaction_pickups before using this
		return $this->belongsTo(TransactionPickupOutlet::class, 'id_transaction_pickup', 'id_transaction_pickup');
    }

    public function logTopup()
    {
    	return $this->belongsTo(LogTopup::class, 'id_transaction', 'transaction_reference');
	}

	public function vouchers()
	{
		return $this->belongsToMany(\App\Http\Models\DealsVoucher::class, 'transaction_vouchers', 'id_transaction', 'id_deals_voucher');
	}

	public function transaction_vouchers()
	{
		return $this->hasMany(\App\Http\Models\TransactionVoucher::class, 'id_transaction', 'id_transaction');
	}

	public function promo_campaign_promo_code()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaignPromoCode::class, 'id_promo_campaign_promo_code', 'id_promo_campaign_promo_code');
	}

	public function promo_campaign_promo_code_delivery()
	{
		return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaignPromoCode::class, 'id_promo_campaign_promo_code_delivery', 'id_promo_campaign_promo_code');
	}

	public function outlet_city()
    {
        return $this->belongsTo(\App\Http\Models\Outlet::class, 'id_outlet')
            ->join('cities','cities.id_city','outlets.id_city');
    }

    public function promo_campaign_referral_transaction()
	{
		return $this->hasOne(\Modules\PromoCampaign\Entities\PromoCampaignReferralTransaction::class, 'id_transaction');
	}

	public static function fillLatestReversalProcess($trxs, $date = null)
	{
		Transaction::whereIn('id_transaction', $trxs->pluck('id_transaction'))->update(['latest_reversal_process' => $date ?: date('Y-m-d H:i:s')]);
	}

	public function clearLatestReversalProcess()
	{
		$update = Transaction::where('id_transaction', $this->id_transaction)->update(['latest_reversal_process' => null]);
	}

	public function cancelOrder($reason, &$errors = [])
	{
		if ($this->transaction_payment_status != 'Completed') {
			$errors[] = 'Transaction payment not complete';
			return false;
		}
		if (!$this->transaction_pickup) {
			$this->load('transaction_pickup');
		}
		if (!$this->outlet) {
			$this->load('outlet');
		}
		if (!$this->user) {
			$this->load('user');
		}
		$pickup = $this->transaction_pickup;
		if ($pickup->receive_at || $pickup->ready_at || $pickup->reject_at || $pickup->taken_at || $pickup->taken_by_system_at) {
			$errors[] = 'Order already processed';
			return false;
		}

        $rejectBalance = false;
        $point = 0;

        \DB::beginTransaction();
        $multiple = TransactionMultiplePayment::where('id_transaction', $this->id_transaction)->get()->toArray();
        foreach ($multiple as $pay) {
            if ($pay['type'] == 'Balance') {
                $payBalance = TransactionPaymentBalance::find($pay['id_payment']);
                if ($payBalance) {
                    $refund = app($this->balance)->addLogBalance($this->id_user, $point = $payBalance['balance_nominal'], $this->id_transaction, 'Rejected Order Point', $this->transaction_grandtotal);
                    if ($refund == false) {
                        DB::rollback();
                        $errors[] = 'Failed refund balance';
                        return false;
                    }
                    $rejectBalance = true;
                }
            } elseif ($pay['type'] == 'Ovo') {
                $payOvo = TransactionPaymentOvo::find($pay['id_payment']);
                if ($payOvo) {
                    if(Configs::select('is_active')->where('config_name','refund ovo')->pluck('is_active')->first()){
                        $point = 0;
                        $transaction = TransactionPaymentOvo::where('transaction_payment_ovos.id_transaction', $pay['id_transaction'])
                            ->join('transactions','transactions.id_transaction','=','transaction_payment_ovos.id_transaction')
                            ->first();
                        $refund = Ovo::Void($transaction);
                        $reject_type = 'refund';
                        if ($refund['status_code'] != '200') {
	                        DB::rollback();
	                        $errors[] = 'Failed refund ovo';
	                        return false;
                        }
                    }else{
                        $refund = app($this->balance)->addLogBalance($this->id_user, $point = $payOvo['amount'], $this->id_transaction, 'Rejected Order Ovo', $this->transaction_grandtotal);
                        if ($refund == false) {
	                        DB::rollback();
	                        $errors[] = 'Failed refund Ovo to balance';
	                        return false;
                        }
                        $rejectBalance = true;
                    }
                }
            } elseif (strtolower($pay['type']) == 'ipay88') {
                $point = 0;
                $payIpay = TransactionPaymentIpay88::find($pay['id_payment']);
                if ($payIpay) {
                    if(strtolower($payIpay['payment_method']) == 'ovo' && MyHelper::setting('refund_ipay88')){
                        $refund = \Modules\IPay88\Lib\IPay88::create()->void($payIpay);
                        $reject_type = 'refund';
                        if (!$refund) {
	                        DB::rollback();
	                        $errors[] = 'Failed refund ipay88';
	                        return false;
                        }
                    }else{
                        $refund = app($this->balance)->addLogBalance($this->id_user, $point = ($payIpay['amount']/100), $this->id_transaction, 'Rejected Order', $this->transaction_grandtotal);
                        if ($refund == false) {
	                        DB::rollback();
	                        $errors[] = 'Failed refund ipay to balance';
	                        return false;
                        }
                        $rejectBalance = true;
                    }
                }
            } elseif (strtolower($pay['type']) == 'shopeepay') {
                $point = 0;
                $payShopeepay = TransactionPaymentShopeePay::find($pay['id_payment']);
                if ($payShopeepay) {
                    if (MyHelper::setting('refund_shopeepay')) {
                        $refund = app($this->shopeepay)->void($payShopeepay['id_transaction'], 'trx', $errors);
                        $reject_type = 'refund';
                        if (!$refund) {
	                        DB::rollback();
	                        $errors[] = 'Failed refund shopeepay';
	                        return false;
                        }
                    }else{
                        $refund = app($this->balance)->addLogBalance($this->id_user, $point = ($payShopeepay['amount']/100), $this->id_transaction, 'Rejected Order', $this->transaction_grandtotal);
                        if ($refund == false) {
	                        DB::rollback();
	                        $errors[] = 'Failed refund shopeepay to balance';
	                        return false;
                        }
                        $rejectBalance = true;
                    }
                }
            } else {
                $point = 0;
                $payMidtrans = TransactionPaymentMidtran::find($pay['id_payment']);
                if ($payMidtrans) {
                    if(MyHelper::setting('refund_midtrans')){
                        $refund = Midtrans::refund($payMidtrans['vt_transaction_id'],['reason' => $post['reason']??'']);
                        $reject_type = 'refund';
                        if ($refund['status'] != 'success') {
	                        DB::rollback();
	                        $errors[] = 'Failed refund midtrans';
	                        return false;
                        }
                    } else {
                        $refund = app($this->balance)->addLogBalance( $this->id_user, $point = $payMidtrans['gross_amount'], $this->id_transaction, 'Rejected Order Midtrans', $this->transaction_grandtotal);
                        if ($refund == false) {
	                        DB::rollback();
	                        $errors[] = 'Failed refund midtrans to balance';
	                        return false;
                        }
                        $rejectBalance = true;
                    }
                }
            }
        }

        //reversal balance
        $logBalance = LogBalance::where('id_reference', $this->id_transaction)->where('source', 'Transaction')->where('balance', '<', 0)->get();
        foreach($logBalance as $logB){
        	$point += abs($logB['balance']);
        	$rejectBalance = true;
            $reversal = app($this->balance)->addLogBalance( $this->id_user, abs($logB['balance']), $this->id_transaction, 'Reversal', $this->transaction_grandtotal);
            if (!$reversal) {
                DB::rollback();
                $errors[] = 'Failed refund to balance';
                return false;
            }
        }
        $pickup->update(['reject_at' => date('Y-m-d H:i:s'), 'reject_reason' => $reason]);
        \DB::commit();
        if ($rejectBalance) {
            $usere= User::where('id',$this->id_user)->first();
            $send = app($this->autocrm)->SendAutoCRM('Transaction Failed Point Refund', $usere->phone,
                [
                    "outlet_name"       => $this->outlet_name->outlet_name,
                    "transaction_date"  => $this->transaction_date,
                    'id_transaction'    => $this->id_transaction,
                    'receipt_number'    => $this->transaction_receipt_number,
                    'received_point'    => (string) $point
                ]
            );
		}

        // delete promo campaign report
    	$update_promo_report = app($this->promo_campaign)->deleteReport($this->id_transaction, $this->id_promo_campaign_promo_code);

        // return voucher
        $update_voucher = app($this->voucher)->returnVoucher($this->id_transaction);
        return true;
	}

	public function getUsedPromoDelivery()
	{
		if ($this->transaction_discount_delivery) {
			if ($this->id_promo_campaign_promo_code_delivery) {
				$promo = \Modules\PromoCampaign\Entities\PromoCampaignPromoCode::where('id_promo_campaign_promo_code', $this->id_promo_campaign_promo_code_delivery)
					->join('promo_campaigns', 'promo_campaign_promo_codes.id_promo_campaign', 'promo_campaigns.id_promo_campaign')
					->first();
				if ($promo) {
					return [
						'type' => 'promo_campaign',
						'model' => $promo,
						'promoDeliveryId' => $promo->promo_code,
						'promoDeliveryDesc' => $promo->promo_title,
					];
				}
			}

			$deals = TransactionVoucher::where('id_transaction', $this->id_transaction)
				->join('deals_vouchers', 'transaction_vouchers.id_deals_voucher', 'deals_vouchers.id_deals_voucher')
				->join('deals', 'deals.id_deals', 'deals_vouchers.id_deals')
				->where('deals.promo_type', 'Discount delivery')
				->first();
			if ($deals) {
				return [
					'type' => 'deals',
					'model' => $deals,
					'promoDeliveryId' => $deals->voucher_code,
					'promoDeliveryDesc' => $deals->deals_title,
				];
			}
		}

		return [
			'type' => null,
			'model' => null,
			'promoDeliveryId' => '',
			'promoDeliveryDesc' => '',
		];
	}
}

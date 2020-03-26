<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;

class DealsPaymentCimb extends Model
{
    protected $primaryKey = 'id_deals_payment_cimb';

    protected $fillable = [
        'id_deals',
        'id_deals_user',
        'transaction_id',
        'amount',
        'txn_status',
        'txn_signature',
        'secure_signature',
        'tran_date',
        'merchant_tranid',
        'response_code',
        'response_desc',
        'auth_id',
        'fr_level',
        'sales_date',
        'fr_score'
    ];
}

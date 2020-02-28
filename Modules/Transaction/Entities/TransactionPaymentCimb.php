<?php

namespace Modules\Transaction\Entities;

use Illuminate\Database\Eloquent\Model;

class TransactionPaymentCimb extends Model
{
    protected $primaryKey = 'id_transaction_payment_cimb';

    protected $casts = [
        'id_transaction' => 'int'
    ];

    protected $fillable = [
        'id_transaction',
        'transaction_id',
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

    public function transaction()
    {
        return $this->belongsTo(\App\Http\Models\Transaction::class, 'id_transaction');
    }
}

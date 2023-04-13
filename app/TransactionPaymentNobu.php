<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionPaymentNobu extends Model
{
    protected $table = 'transaction_payment_nobu';

    protected $primaryKey = 'id_transaction_payment_nobu';

	protected $casts = [
		'id_transaction' => 'int'
	];

	protected $fillable = [
		'id_transaction',
		'bank',
		'transaction_time',
		'gross_amount',
		'order_id',
		'payment_type',
		'signature_key',
		'status_code',
		'vt_transaction_id',
		'transaction_status',
		'fraud_status',
		'status_message'
	];

	public function transaction()
	{
		return $this->belongsTo(\App\Http\Models\Transaction::class, 'id_transaction');
	}
    
}

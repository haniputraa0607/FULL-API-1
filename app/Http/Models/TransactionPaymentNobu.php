<?php

namespace App\Http\Models;

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
		'no_transaction',
		'no_transaction_reference',
		'qris_data',
		'transaction_time',
		'gross_amount',
		'order_id',
		'payment_type',
		'status_code',
		'transaction_status',
		'status_message',
		'payment_status',
		'payment_reference_no',
		'payment_date',
		'id_issuer',
		'retrieval_reference_no',
	];

	public function transaction()
	{
		return $this->belongsTo(\App\Http\Models\Transaction::class, 'id_transaction');
	}
    
}

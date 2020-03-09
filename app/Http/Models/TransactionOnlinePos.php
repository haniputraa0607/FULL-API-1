<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionOnlinePos extends Model
{
	public $primaryKey = 'id_transaction_online_pos';
	protected $table = 'transactions_online_pos';

	protected $fillable = [
		'id_transaction_online_pos',
		'id_transaction',
		'request',
		'response',
		'count_retry',
        'success_retry_status',
        'send_email_status',
        'created_at',
        'updated_at'
	];
}

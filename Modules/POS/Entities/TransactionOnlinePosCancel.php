<?php

namespace Modules\POS\Entities;

use Illuminate\Database\Eloquent\Model;

class TransactionOnlinePosCancel extends Model
{
	public $primaryKey = 'id_transaction_online_pos_cancel';

	protected $fillable = [
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

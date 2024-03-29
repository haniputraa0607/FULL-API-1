<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class FraudDetectionLog extends Model
{
	protected $primaryKey = 'id_fraud_detection_log';
	protected $table = 'fraud_detection_logs';

	protected $fillable = [
		'id_user',
		'id_fraud_setting',
		'count_transaction_day',
		'count_transaction_week',
		'id_transaction',
		'id_device_user'
	];

	function user() {
		return $this->belongsTo(User::class, 'id_user', 'id');
	}

	function transaction() {
		return $this->belongsTo(Transaction::class, 'id_transaction', 'id_transaction');
	}

	function userDevice() {
		return $this->belongsTo(UserDevice::class, 'id_device_user', 'id_device_user');
	}

}

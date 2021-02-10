<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionPickup extends Model
{
    protected $primaryKey = 'id_transaction_pickup';

	protected $casts = [
		'id_transaction' => 'int'
	];

	protected $fillable = [
		'id_transaction',
		'order_id',
		'short_link',
		'pickup_by',
		'pickup_type',
		'pickup_at',
		'receive_at',
		'ready_at',
		'taken_at',
		'taken_by_system_at',
		'reject_at',
		'reject_reason',
		'id_admin_outlet_receive',
		'id_admin_outlet_taken',
		'created_at',
		'updated_at'
	];

	public function transaction()
	{
		return $this->belongsTo(\App\Http\Models\Transaction::class, 'id_transaction');
	}

	public function transaction_pickup_go_send()
	{
		return $this->hasOne(\App\Http\Models\TransactionPickupGoSend::class, 'id_transaction_pickup');
	}
	
	public function admin_receive() 
	{
		return $this->belongsTo(UserOutlet::class, 'id_admin_outlet_receive', 'id_user_outlet');
	}

	public function admin_taken() 
	{
		return $this->belongsTo(UserOutlet::class, 'id_admin_outlet_taken', 'id_user_outlet');
	}

	public function bookDelivery(&$errors = [])
	{
		switch ($this->pickup_by) {
			case 'Customer':
				$errors[] = 'Transaction pickup by Customer';
				return false;
				break;
			
			case 'GO-SEND':
				$pickup_go_send = TransactionPickupGoSend::where('id_transaction_pickup', $this->id_transaction_pickup)->first();
				if ($pickup_go_send) {
					$book = $pickup_go_send->book($errors);
					if (!$book) {
						$this->load(['transaction', 'transaction.outlet']);
						$trx = $this->transaction;
						$outlet = $trx->outlet;
		                $autocrm = app("Modules\Autocrm\Http\Controllers\ApiAutoCrm")->SendAutoCRM('Delivery Status Update', $phone,
		                    [
		                        'id_reference'    => $trx->id_transaction,
		                        'receipt_number'  => $trx->transaction_receipt_number,
		                        'outlet_code'     => $outlet->outlet_code,
		                        'outlet_name'     => $outlet->outlet_name,
		                        'delivery_status' => 'Belum berhasil menemukan driver',
		                        'order_id'        => $this->order_id,
		                    ]
		                );                
					}
					return $book;
				}
				break;
			
			default:
				# code...
				break;
		}
	}
}

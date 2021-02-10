<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use App\Lib\MyHelper;
use App\Lib\GoSend;

class TransactionPickupGoSend extends Model
{
    protected $primaryKey = 'id_transaction_pickup_go_send';

	protected $casts = [
		'id_transaction_pickup' => 'int'
	];

    protected $fillable = [
        'id_transaction_pickup',
        'origin_name',
        'origin_phone',
        'origin_address',
        'origin_note',
        'origin_latitude',
        'origin_longitude',
        'destination_name',
        'destination_phone',
        'destination_address',
        'destination_address_name',
        'destination_short_address',
        'destination_note',
        'destination_latitude',
        'destination_longitude',
        'go_send_id',
        'go_order_no',
        'latest_status',
        'cancel_reason',
        'live_tracking_url',
        'driver_id',
        'driver_name',
        'driver_phone',
        'driver_photo',
        'vehicle_number',
        'receiver_name',
        'retry_count',
        'stop_booking_at',
        'created_at',
        'updated_at',
        'manual_order_no',
    ];

    public function transaction_pickup()
    {
        return $this->belongsTo(\App\Http\Models\TransactionPickup::class, 'id_transaction_pickup');
    }
    
    public function transaction_pickup_update()
    {
        return $this->hasMany(TransactionPickupGoSendUpdate::class, 'id_transaction_pickup_go_send')->orderBy('created_at', 'DESC')->orderBy('id_transaction_pickup_go_send_update', 'DESC');
    }

	public function book($fromRetry = false, &$errors = []) {
		$trx = Transaction::join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')->where('id_transaction_pickup', $this->id_transaction_pickup)->with('outlet')->first();
        //create booking GO-SEND
        $origin['name']      = $trx['outlet']['outlet_name'];
        $origin['phone']     = $trx['outlet']['outlet_phone'];
        $origin['latitude']  = $trx['outlet']['outlet_latitude'];
        $origin['longitude'] = $trx['outlet']['outlet_longitude'];
        $origin['address']   = $trx['outlet']['outlet_address'] . '. ' . $this['origin_note'];
        $origin['note']      = $this['origin_note'];

        $destination['name']      = $this['destination_name'];
        $destination['phone']     = $this['destination_phone'];
        $destination['latitude']  = $this['destination_latitude'];
        $destination['longitude'] = $this['destination_longitude'];
        $destination['address']   = $this['destination_address'];
        $destination['note']      = $this['destination_note'];

        $packageDetail = Setting::where('key', 'go_send_package_detail')->first();

        //update id from go-send
        $maxRetry = Setting::select('value')->where('key', 'booking_delivery_max_retry')->pluck('value')->first()?:5;
        if ($fromRetry && $this->retry_count >= $maxRetry) {
            $errors[] = 'Retry reach limit';
            return false;
        }

        if ($packageDetail) {
            $packageDetail = str_replace('%order_id%', $trx['order_id'], $packageDetail['value']);
        } else {
            $packageDetail = "Order " . $trx['order_id'];
        }

        $booking = GoSend::booking($origin, $destination, $packageDetail, $trx['transaction_receipt_number']);
        if (isset($booking['status']) && $booking['status'] == 'fail') {
            $errors = array_merge($errors, $booking['messages']);
            // send notification here
            return false;
        }

        if (!isset($booking['id'])) {
            $errors = array_merge($errors, $booking['messages'] ?? ['failed booking GO-SEND']);
            return false;
        }
        $ref_status = [
            'Finding Driver' => 'confirmed',
            'Driver Allocated' => 'allocated',
            'Enroute Pickup' => 'out_for_pickup',
            'Item Picked by Driver' => 'picked',
            'Enroute Drop' => 'out_for_delivery',
            'Cancelled' => 'cancelled',
            'Completed' => 'delivered',
            'Rejected' => 'rejected',
            'Driver not found' => 'no_driver',
            'On Hold' => 'on_hold',
        ];
        $status = GoSend::getStatus($booking['orderNo'], true);
        $status['status'] = $ref_status[$status['status']] ?? $status['status'];
        $dataSave     = [
            'id_transaction'                => $trx['id_transaction'],
            'id_transaction_pickup_go_send' => $this['id_transaction_pickup_go_send'],
            'status'                        => $status['status'] ?? 'Finding Driver',
            'go_send_order_no'              => $booking['orderNo']
        ];
        GoSend::saveUpdate($dataSave);

        $this->go_send_id        = $booking['id'];
        $this->go_send_order_no  = $booking['orderNo'];
        $this->latest_status     = $status['status'] ?? null;
        $this->driver_id         = $status['driverId'] ?? null;
        $this->driver_name       = $status['driverName'] ?? null;
        $this->driver_phone      = $status['driverPhone'] ?? null;
        $this->driver_photo      = $status['driverPhoto'] ?? null;
        $this->vehicle_number    = $status['vehicleNumber'] ?? null;
        $this->live_tracking_url = $status['liveTrackingUrl'] ?? null;
        $this->retry_count = $fromRetry?($this->retry_count+1):0;
        $this->save();

        return true;
	}
}

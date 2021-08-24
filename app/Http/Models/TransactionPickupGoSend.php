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

    protected $trx = 'Modules\Transaction\Http\Controllers\ApiOnlineTransaction';
    protected $autocrm = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
    protected $getNotif = "Modules\Transaction\Http\Controllers\ApiNotification";
    protected $membership = "Modules\Membership\Http\Controllers\ApiMembership";

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

        if ($this->latest_status && !in_array($this->latest_status, ['cancelled', 'rejected', 'no_driver'])) {
            $errors[] = 'Unable book gosend. Latest status: '.$this->latest_status;
            return false;
        }
        //create booking GO-SEND
        $origin['name']      = $trx['outlet']['outlet_name'];
        $origin['phone']     = $trx['outlet']['outlet_phone'];
        $origin['latitude']  = $trx['outlet']['outlet_latitude'];
        $origin['longitude'] = $trx['outlet']['outlet_longitude'];
        $origin['address']   = $trx['outlet']['outlet_address'];
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
            if (!$this->stop_booking_at) {
                $this->update(['stop_booking_at' => date('Y-m-d H:i:s')]);
            }
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
            $errors = array_merge($errors??[], $booking['messages']);
            // send notification here
            return false;
        }

        if (!isset($booking['id'])) {
            $errors = array_merge($errors??[], $booking['messages'] ?? ['failed booking GO-SEND']);
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
        $this->retry_count       = $fromRetry?($this->retry_count+1):0;
        $this->manual_order_no   = $fromRetry?$this->manual_order_no:$booking['orderNo'];
        $this->stop_booking_at   = null;
        $this->save();

        return true;
	}

    public function refreshDeliveryStatus()
    {
        $trx = Transaction::where('transaction_pickups.id_transaction_pickup', $this->id_transaction_pickup)->join('transaction_pickups', 'transaction_pickups.id_transaction', '=', 'transactions.id_transaction')->with(['outlet' => function($q) {
            $q->select('id_outlet', 'outlet_name');
        }])->first();
        $outlet = $trx->outlet;
        if (!$trx) {
            return MyHelper::checkGet($trx, 'Transaction Not Found');
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
        $status = GoSend::getStatus($trx['transaction_receipt_number']);
        $status['status'] = $ref_status[$status['status']]??$status['status'];
        if($status['receiver_name'] ?? '') {
            $toUpdate['receiver_name'] = $status['receiver_name'];
        }
        if ($status['status'] ?? false) {
            $toUpdate = ['latest_status' => $status['status']];
            if ($status['liveTrackingUrl'] ?? false) {
                $toUpdate['live_tracking_url'] = $status['liveTrackingUrl'];
            }
            if ($status['driverId'] ?? false) {
                $toUpdate['driver_id'] = $status['driverId'];
            }
            if ($status['driverName'] ?? false) {
                $toUpdate['driver_name'] = $status['driverName'];
            }
            if ($status['driverPhone'] ?? false) {
                $toUpdate['driver_phone'] = $status['driverPhone'];
            }
            if ($status['driverPhoto'] ?? false) {
                $toUpdate['driver_photo'] = $status['driverPhoto'];
            }
            if ($status['vehicleNumber'] ?? false) {
                $toUpdate['vehicle_number'] = $status['vehicleNumber'];
            }
            if (!in_array(strtolower($status['status']), ['confirmed', 'no_driver', 'cancelled']) && strpos(env('GO_SEND_URL'), 'integration')) {
                $toUpdate['driver_id']      = '00510001';
                $toUpdate['driver_phone']   = '08111251307';
                $toUpdate['driver_name']    = 'Anton Lucarus';
                $toUpdate['driver_photo']   = 'http://beritatrans.com/cms/wp-content/uploads/2020/02/images4-553x400.jpeg';
                $toUpdate['vehicle_number'] = 'AB 2641 XY';
            }
            if (!($status['status'] == 'allocated' && $this->latest_status == 'out_for_pickup')) {
                $this->update($toUpdate);
            }

            if (in_array(strtolower($status['status']), ['allocated', 'out_for_pickup', 'out_for_delivery', 'completed', 'delivered'])) {
                \App\Lib\ConnectPOS::create()->sendTransaction($trx['id_transaction']);
            }

            if (in_array(strtolower($status['status']), ['completed', 'delivered'])) {
                // sendPoint delivery after status delivered only
                if ($trx->cashback_insert_status != 1) {
                    //send notif to customer
                    $user = User::find($trx->id_user);

                    $newTrx    = Transaction::with('user.memberships', 'outlet', 'productTransaction', 'transaction_vouchers','promo_campaign_promo_code','promo_campaign_promo_code.promo_campaign')->where('id_transaction', $trx->id_transaction)->first();
                    $checkType = TransactionMultiplePayment::where('id_transaction', $trx->id_transaction)->get()->toArray();
                    $column    = array_column($checkType, 'type');
                    
                    $use_referral = optional(optional($newTrx->promo_campaign_promo_code)->promo_campaign)->promo_type == 'Referral';

                    if (!in_array('Balance', $column) || $use_referral) {

                        $promo_source = null;
                        if ($newTrx->id_promo_campaign_promo_code || $newTrx->transaction_vouchers) {
                            if ($newTrx->id_promo_campaign_promo_code) {
                                $promo_source = 'promo_code';
                            } elseif (($newTrx->transaction_vouchers[0]->status ?? false) == 'success') {
                                $promo_source = 'voucher_online';
                            }
                        }

                        if (app($this->trx)->checkPromoGetPoint($promo_source) || $use_referral) {
                            $savePoint = app($this->getNotif)->savePoint($newTrx);
                            // return $savePoint;
                            if (!$savePoint) {
                                return response()->json([
                                    'status'   => 'fail',
                                    'messages' => ['Transaction failed'],
                                ]);
                            }
                        }

                    }

                    $newTrx->update(['cashback_insert_status' => 1]);
                    $checkMembership = app($this->membership)->calculateMembership($user['phone']);
                }
                $arrived_at = date('Y-m-d H:i:s', ($status['orderArrivalTime']??false)?strtotime($status['orderArrivalTime']):time());
                TransactionPickup::where('id_transaction', $trx->id_transaction)->update(['taken_at' => $arrived_at]);
                $dataSave = [
                    'id_transaction'                => $trx['id_transaction'],
                    'id_transaction_pickup_go_send' => $this['id_transaction_pickup_go_send'],
                    'status'                        => $status['status'] ?? 'on_going',
                    'go_send_order_no'              => $status['orderNo'] ?? ''
                ];
                GoSend::saveUpdate($dataSave);
            } elseif (in_array(strtolower($status['status']), ['allocated', 'out_for_pickup'])) {

                $mid = [
                    'order_id' => $trx->transaction_receipt_number,
                    'gross_amount' => $trx->transaction_grandtotal
                ];

                $dataSave = [
                    'id_transaction'                => $trx['id_transaction'],
                    'id_transaction_pickup_go_send' => $this['id_transaction_pickup_go_send'],
                    'status'                        => $status['status'] ?? 'on_going',
                    'go_send_order_no'              => $status['orderNo'] ?? ''
                ];
                GoSend::saveUpdate($dataSave);

                $trx->load(['user', 'outlet', 'productTransaction']);

                app('Modules\Transaction\Http\Controllers\ApiNotification')->notification($mid, $trx, true);
            } elseif (in_array(strtolower($status['status']), ['no_driver'])) {
                $this->update([
                    'live_tracking_url' => null,
                    'driver_id' => null,
                    'driver_name' => null,
                    'driver_phone' => null,
                    'driver_photo' => null,
                    'vehicle_number' => null,
                    'receiver_name' => null
                ]);
                $dataSave = [
                    'id_transaction'                => $trx['id_transaction'],
                    'id_transaction_pickup_go_send' => $this['id_transaction_pickup_go_send'],
                    'status'                        => $status['status'] ?? 'on_going',
                    'go_send_order_no'              => $status['orderNo'] ?? ''
                ];
                GoSend::saveUpdate($dataSave);
                $this->book(true);
            } elseif (in_array(strtolower($status['status']), ['cancelled', 'rejected'])) {
                $dataSave = [
                    'id_transaction'                => $trx['id_transaction'],
                    'id_transaction_pickup_go_send' => $this['id_transaction_pickup_go_send'],
                    'status'                        => $status['status'] ?? 'on_going',
                    'go_send_order_no'              => $status['orderNo'] ?? '',
                    'description'                   => $status['cancelDescription'] ?? null
                ];
                GoSend::saveUpdate($dataSave);
                // masuk flow rejected
                $cancel = $trx->cancelOrder('auto reject order by system [delivery '.strtolower($status['status']).']', $errors);

                if (!$cancel) {
                    \Log::error('Failed cancel order gosend for '.$trx->transaction_receipt_number, $errors ?: []);
                } else {
                    \App\Lib\ConnectPOS::create()->sendCancelOrder($trx);
                }
            } else {
                $dataSave = [
                    'id_transaction'                => $trx['id_transaction'],
                    'id_transaction_pickup_go_send' => $this['id_transaction_pickup_go_send'],
                    'status'                        => $status['status'] ?? 'on_going',
                    'go_send_order_no'              => $status['orderNo'] ?? ''
                ];
                GoSend::saveUpdate($dataSave);
            }
        }
        return MyHelper::checkGet($this);
    }
}

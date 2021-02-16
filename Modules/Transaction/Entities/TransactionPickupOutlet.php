<?php

namespace Modules\Transaction\Entities;

use Illuminate\Database\Eloquent\Model;

class TransactionPickupOutlet extends Model
{

    protected $primaryKey = 'id_transaction_pickup_outlet';

    protected $fillable = [
            'id_transaction_pickup',
            'id_transaction',
            'id_user_address',
            'destination_address',
            'destination_address_name',
            'destination_short_address',
            'destination_note',
            'destination_latitude',
            'destination_longitude',
            'distance',
    ];
}

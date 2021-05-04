<?php

namespace Modules\Transaction\Entities;

use Illuminate\Database\Eloquent\Model;

class TransactionVoidFailed extends Model
{
    protected $primaryKey = 'id_transaction_void_failed';
    protected $fillable = [
        'id_transaction',
        'id_payment',
        'payment_type',
        'retry_status',
        'retry_count',
    ];
}

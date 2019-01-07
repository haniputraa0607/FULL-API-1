<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportTrx extends Model
{
	protected $connection = 'mysql';
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'daily_report_trx';

    protected $primaryKey = 'id_report_trx';

    /**
     * @var array
     */
    protected $fillable = [
        'id_outlet',
        'trx_date',
        'trx_count',
        'trx_tax',
        'trx_shipment',
        'trx_service',
        'trx_discount',
        'trx_subtotal',
        'trx_grand',
        'trx_cashback_earned',
        'trx_point_earned',
        'trx_max',
        'trx_average'
    ];
	
	/**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
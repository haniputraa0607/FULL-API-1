<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class OutletSchedule extends Model
{
	use Userstamps;
    protected $primaryKey = 'id_outlet_schedule';

    protected $fillable = [
		'id_outlet',
		'day',
		'open',
		'close',
		'is_closed',
		'time_zone',
		'created_at',
		'updated_at',
	];

    public function getOpenAttribute($value) {
		return date('H:i', strtotime($value));
	}

	public function getCloseAttribute($value) {
		return date('H:i', strtotime($value));
	}

	public function outlet()
	{
		return $this->belongsTo(\App\Http\Models\Outlet::class, 'id_outlet');
	}
}

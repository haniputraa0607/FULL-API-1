<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;
use Reliese\Database\Eloquent\Model as Eloquent;


class DealsDay extends Eloquent
{
    use Userstamps;
	protected $table = 'deals_days';
	protected $primaryKey = 'id_deals_day';

	protected $casts = [
		'id_deals' => 'int',
	];

	protected $fillable = [
		'id_deals',
		'day'
	];

	public function deals()
	{
		return $this->belongsTo(\App\Http\Models\Deal::class, 'id_deals');
	}
}

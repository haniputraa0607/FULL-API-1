<?php

namespace Modules\Deals\Entities;

use Illuminate\Database\Eloquent\Model;

class SecondDealsTotal extends Model
{
    protected $primaryKey = 'id_second_deals_total';
    protected $table = 'second_deals_totals';

	protected $fillable = [
		'id_deals',
		'deals_total',
		'created_by',
		'updated_by'
	];
}

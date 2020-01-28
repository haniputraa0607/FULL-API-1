<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class ProvinceCustom extends Model
{
	protected $primaryKey = 'id_province_custom';
	public $timestamps = false;

	protected $fillable = [
		'province_name'
	];

}

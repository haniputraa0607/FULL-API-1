<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductModifierBrand extends Model
{
	use Userstamps;
	public $timestamps = false;
    protected $fillable = [
    	'id_brand',
    	'id_product_modifier'
    ];
}

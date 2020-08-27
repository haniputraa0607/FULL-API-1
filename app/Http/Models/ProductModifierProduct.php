<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductModifierProduct extends Model
{
	use Userstamps;
	public $timestamps = false;
    protected $fillable = [
    	'id_product',
    	'id_product_modifier'
    ];
}

<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductModifierProductCategory extends Model
{
	use Userstamps;
	public $timestamps = false;
    protected $fillable = [
    	'id_product_category',
    	'id_product_modifier'
    ];
}

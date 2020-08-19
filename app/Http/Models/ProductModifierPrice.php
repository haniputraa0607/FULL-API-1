<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductModifierPrice extends Model
{
	use Userstamps;
	protected $primaryKey = 'id_product_modifier_price';
    protected $fillable = [
    	'id_product_modifier',
    	'id_outlet',
    	'product_modifier_price',
    	'product_modifier_visibility',
    	'product_modifier_status',
    	'product_modifier_stock_status',
    ];
}

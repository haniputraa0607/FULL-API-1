<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductGroupProductPromoCategory extends Model
{
	use Userstamps;
	public $timestamps = false;
    protected $fillable = [
    	'id_product_group',
    	'id_product_promo_category',
    	'position'
    ];
}

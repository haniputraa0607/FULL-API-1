<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductGroupProductPromoCategory extends Model
{
	public $timestamps = false;
    protected $fillable = [
    	'id_product_group',
    	'id_product_promo_category'
    ];
}

<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductProductVariant extends Model
{
	public $timestamps = false;
    protected $fillable   = [
        'id_product',
        'id_product_variant',
        'id_product_group',
        'product_variant_price'
    ];
    public function product_groups()
    {
    	return $this->belongsTo(ProductGroup::class,'id_product_group','id_product_group');
    }
}

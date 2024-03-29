<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductProductVariant extends Model
{
	use Userstamps;
	public $timestamps = false;
    protected $fillable   = [
        'id_product',
        'id_product_variant',
        'product_variant_price'
    ];
    public function product_groups()
    {
    	return $this->belongsTo(ProductGroup::class,'id_product_group','id_product_group');
    }
}

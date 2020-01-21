<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $primaryKey = 'id_product_variant';

    protected $fillable   = [
        'product_variant_code',
        'product_variant_description',
        'product_variant_name',
        'product_variant_position',
        'parent'
    ];

    public function products()
    {
    	return $this->belongsToMany(\App\Http\Models\ProductProductVariant::class,'id_product_variant','id_product');
    }
}

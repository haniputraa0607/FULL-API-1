<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductProductVariant extends Model
{
    protected $fillable   = [
        'id_product',
        'id_product_variant',
        'product_variant_price'
    ];
}

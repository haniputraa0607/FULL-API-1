<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $primaryKey = 'id_product_group';

    protected $fillable   = [
        'product_group_name',
        'product_group_description',
        'product_group_photo',
    ];

    public function products()
    {
    	return $this->hasMany(\App\Http\Models\Product::class,'id_product_group','id_product_group');
    }
}

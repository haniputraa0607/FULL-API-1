<?php

namespace Modules\Brand\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class BrandProduct extends Model
{
	use Userstamps;
    protected $table = 'brand_product';

    protected $primaryKey = 'id_brand_product';

    protected $fillable   = [
        'id_brand',
        'id_product',
        'id_product_category'
    ];

    public function products(){
        return $this->belongsTo(\App\Http\Models\Product::class, 'id_product', 'id_product');
    }
}

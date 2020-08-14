<?php

namespace Modules\CustomPage\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Http\Models\Product;
use Wildside\Userstamps\Userstamps;

class CustomPageProduct extends Model
{
	use Userstamps;
    protected $table = 'custom_page_products';

    protected $fillable = [
        'id_custom_page',
        'id_product'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product');
    }
}

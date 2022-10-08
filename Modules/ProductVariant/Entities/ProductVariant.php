<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductVariant extends Model
{
	use Userstamps;
    protected $primaryKey = 'id_product_variant';

    protected $fillable   = [
        'product_variant_code',
        'product_variant_subtitle',
        'product_variant_name',
        'product_variant_title',
        'product_variant_position',
        'parent'
    ];

    public function products()
    {
    	return $this->belongsToMany(\App\Http\Models\ProductProductVariant::class,'id_product_variant','id_product');
    }

    public function parent()
    {
        return $this->belongsTo(ProductVariant::class,'parent','id_product_variant');
    }

    public function children()
    {
        return $this->hasMany(ProductVariant::class, 'parent','id_product_variant');
    }

    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }
}

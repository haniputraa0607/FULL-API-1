<?php

namespace Modules\ProductVariant\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $primaryKey = 'id_product_group';

    protected $fillable   = [
        'id_product_category',
        'product_group_code',
        'product_group_name',
        'product_group_description',
        'product_group_photo',
        'product_group_image_detail'
    ];

    public function products()
    {
    	return $this->hasMany(\App\Http\Models\Product::class,'id_product_group','id_product_group');
    }
    public function getProductGroupPhotoAttribute($value)
    {
        if($value){
            return env('S3_URL_API').$value;
        }
        return env('S3_URL_API').'img/product/item/default.png';
    }
    public function getProductGroupImageDetailAttribute($value)
    {
        if($value){
            return env('S3_URL_API').$value;
        }
        return env('S3_URL_API').'img/product/item/default.png';
    }
}

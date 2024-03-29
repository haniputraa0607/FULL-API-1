<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class ProductPhoto extends Model
{
	use Userstamps;
	protected $connection = 'mysql';
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'product_photos';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'id_product_photo';
    
    protected $appends    = ['url_product_photo'];

    /**
     * @var array
     */
    protected $fillable = ['id_product', 'product_photo', 'product_photo_order', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product');
    }

    public function getUrlProductPhotoAttribute() {
        if (empty($this->product_photo)) {
            return env('S3_URL_API').'img/default.jpg';
        }
        else {
            return env('S3_URL_API').$this->product_photo;
        }
    }
}

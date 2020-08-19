<?php

namespace Modules\CustomPage\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class CustomPageImage extends Model
{
	use Userstamps;
    protected $table = 'custom_page_images';

    protected $primaryKey = 'id_custom_page_image';

    protected $fillable = [
        'id_custom_page',
        'custom_page_image',
        'image_order'
    ];
}

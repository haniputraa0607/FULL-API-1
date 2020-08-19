<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class NewsCategory extends Model
{
	use Userstamps;
    protected $primaryKey = 'id_news_category';

    /**
     * @var array
     */
    protected $fillable = [
    	'category_name',
	];

	public function news(){
		return $this->hasMany(\App\Http\Models\News::class,'id_news_category','id_news_category');
	}
}

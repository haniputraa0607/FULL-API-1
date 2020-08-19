<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class NewsFormData extends Model
{
	use Userstamps;
	protected $connection = 'mysql';
    protected $table = 'news_form_datas';
    protected $primaryKey = 'id_news_form_data';

    protected $fillable = ['id_news', 'id_user'];


	public function user(){
		return $this->hasOne(User::class, 'id', 'id_user')->select('id', 'name');
	}

    public function news_form_data_details(){
		return $this->hasMany(NewsFormDataDetail::class, 'id_news_form_data', 'id_news_form_data');
	}
}

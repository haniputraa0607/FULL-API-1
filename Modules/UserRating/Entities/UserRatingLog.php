<?php

namespace Modules\UserRating\Entities;

use Illuminate\Database\Eloquent\Model;

class UserRatingLog extends Model
{
	protected $primaryKey = 'id_user_rating_log';
    protected $fillable = ['id_user','last_popup','refuse_count'];
}

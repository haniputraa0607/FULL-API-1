<?php

namespace Modules\UserRating\Entities;

use Illuminate\Database\Eloquent\Model;

class UserRating extends Model
{
    protected $primaryKey = 'id_user_rating';
    protected $fillable = ['id_user','id_transaction','rating_value','sugestion','option_value'];
}

<?php

namespace Modules\UserRating\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class RatingOption extends Model
{
	use Userstamps;
    protected $primaryKey = 'id_rating_option';
    protected $fillable = ['star','question','options'];
}

<?php

namespace Modules\UserRating\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class UserRating extends Model
{
	use Userstamps;
    protected $primaryKey = 'id_user_rating';
    protected $fillable = ['id_user','id_transaction','option_question','rating_value','suggestion','option_value'];
    public function transaction() {
    	return $this->belongsTo(\App\Http\Models\Transaction::class,'id_transaction','id_transaction');
    }
    public function user()
    {
        return $this->belongsTo(\App\Http\Models\User::class,'id_user','id');
    }
}

<?php

namespace Modules\PointInjection\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class PointInjectionUser extends Model
{
	use Userstamps;
    protected $table = 'point_injection_users';

    protected $fillable = [
        'id_point_injection',
        'id_user',
        'total_point'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Http\Models\User::class, 'id_user');
    }
}

<?php

namespace Modules\Balance\Entities;

use Illuminate\Database\Eloquent\Model;

class AdjustmentPointUser extends Model
{
    protected $primaryKey = 'id_adjustment_point_user';
    protected $fillable = [
        'id_user',
        'point_adjust',
        'reason'
    ];
}

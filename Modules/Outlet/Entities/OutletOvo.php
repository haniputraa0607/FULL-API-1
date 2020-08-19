<?php

namespace Modules\Outlet\Entities;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class OutletOvo extends Model
{
	use Userstamps;
    protected $table = 'outlet_ovos';

    protected $fillable   = [
        'id_outlet',
        'store_code',
        'tid',
        'mid'
    ];
}

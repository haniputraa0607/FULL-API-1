<?php

namespace Modules\Outlet\Entities;

use Illuminate\Database\Eloquent\Model;

class OutletOvo extends Model
{
    protected $table = 'outlet_ovos';

    protected $fillable   = [
        'id_outlet',
        'store_code',
        'tid',
        'mid'
    ];
}

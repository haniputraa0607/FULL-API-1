<?php

namespace Modules\CustomPage\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Http\Models\Outlet;
use Wildside\Userstamps\Userstamps;

class CustomPageOutlet extends Model
{
	use Userstamps;
    protected $table = 'custom_page_outlets';

    protected $fillable = [
        'id_custom_page',
        'id_outlet'
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}

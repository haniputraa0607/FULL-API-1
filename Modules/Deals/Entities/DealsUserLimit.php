<?php

/**
 * Created by Reliese Model.
 * Date: Wed, 22 Apr 2020 13:58:29 +0700.
 */

namespace Modules\Deals\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class DealsUserLimit
 * 
 * @property int $id_deals_user_limit
 * @property int $id_user
 * @property int $id_deals
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property \App\Models\Deal $deal
 * @property \App\Models\User $user
 *
 * @package App\Models
 */
class DealsUserLimit extends Eloquent
{
	protected $primaryKey = 'id_deals_user_limit';

	protected $casts = [
		'id_user' => 'int',
		'id_deals' => 'int'
	];

	protected $fillable = [
		'id_user',
		'id_deals'
	];

	public function deal()
	{
		return $this->belongsTo(\App\Http\Models\Deal::class, 'id_deals');
	}

	public function user()
	{
		return $this->belongsTo(\App\Http\Models\User::class, 'id_user');
	}
}

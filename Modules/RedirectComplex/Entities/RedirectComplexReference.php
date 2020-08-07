<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 06 Aug 2020 15:39:29 +0700.
 */

namespace Modules\RedirectComplex\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class RedirectComplexReference
 * 
 * @property int $id_redirect_complex_reference
 * @property string $type
 * @property string $outlet_type
 * @property string $promo_type
 * @property string $promo_reference
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property \Illuminate\Database\Eloquent\Collection $redirect_complex_outlets
 * @property \Illuminate\Database\Eloquent\Collection $redirect_complex_products
 *
 * @package Modules\RedirectComplex\Entities
 */
class RedirectComplexReference extends Eloquent
{
	protected $primaryKey = 'id_redirect_complex_reference';

	protected $fillable = [
		'type',
		'name',
		'outlet_type',
		'promo_type',
		'promo_reference'
	];

	public function redirect_complex_outlets()
	{
		return $this->hasMany(\Modules\RedirectComplex\Entities\RedirectComplexOutlet::class, 'id_redirect_complex_reference');
	}

	public function redirect_complex_products()
	{
		return $this->hasMany(\Modules\RedirectComplex\Entities\RedirectComplexProduct::class, 'id_redirect_complex_reference');
	}
}

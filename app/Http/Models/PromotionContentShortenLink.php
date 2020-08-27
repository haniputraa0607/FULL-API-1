<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:15 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class PromotionContentShortenLink extends Model
{
	use Userstamps;
	protected $primaryKey = 'id_promotion_content_shorten_link';

	protected $casts = [
		'id_promotion_content' => 'int',
	];

	protected $fillable = [
		'id_promotion_content',
		'original_link',
		'short_link',
		'type',
		'created_at',
		'updated_at',
	];

	public function promotionContent()
	{
		return $this->belongsTo(\App\Http\Models\PromotionContent::class, 'id_promotion');
	}
}

<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class PromotionRuleParent extends Model
{
	use Userstamps;
    protected $primaryKey = 'id_promotion_rule_parent';

	protected $casts = [
		'id_promotion' => 'int'
	];

	protected $fillable = [
		'id_promotion',
		'promotion_rule',
		'promotion_rule_next',
	];

	public function promotion()
	{
		return $this->belongsTo(\App\Http\Models\Promotion::class, 'id_promotion');
	}

	public function rules()
	{
		return $this->hasMany(\App\Http\Models\PromotionRule::class, 'id_promotion_rule_parent')
					->select('id_promotion_rule','id_promotion_rule_parent','promotion_rule_subject as subject', 'promotion_rule_operator as operator', 'promotion_rule_param as parameter', 'promotion_rule_param_id as id');
	}
}

<?php

namespace Modules\PromoCampaign\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ImportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [

        	'date_start'               => 'required|date|after_or_equal:'.date('Y-m-d').'',
            'date_end'                 => 'required|date|after_or_equal:date_start',

            'data.rule.campaign_name' 	=> 'required',
            'data.rule.promo_title' 	=> 'required',
            'data.rule.code_type' 		=> 'required',
            'data.rule.total_coupon' 	=> 'required',
            'data.rule.is_all_outlet' 	=> 'required',
            'data.rule.product_type' 	=> 'required',
            'data.rule.limitation_usage' 	=> 'required',

            'data.outlet'	=> 'nullable',
            'data.tags'	=> 'nullable',
            'data.detail_rule_product_discount'	=> 'nullable',
            'data.detail_rule_tier_discount'	=> 'nullable',
            'data.detail_rule_buyxgety_discount'=> 'nullable'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['status' => 'fail', 'messages'  => $validator->errors()->all()], 200));
    }

    protected function validationData()
    {
        return $this->json()->all();
    }
}

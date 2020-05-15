<?php

namespace Modules\Deals\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ImportDealsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
        	'deals_type'				=> 'required',
        	'deals_start'               => 'sometimes|nullable|date|after_or_equal:'.date('Y-m-d').'',
            'deals_end'                 => 'sometimes|nullable|date|after_or_equal:deals_start',
            'deals_publish_start'       => 'sometimes|nullable|date',
            'deals_publish_end'         => 'sometimes|nullable|date|after_or_equal:deals_publish_start',
            'deals_voucher_start'     	=> 'nullable|date|after:deals_start',
            'deals_voucher_expired'     => 'nullable|date|after:deals_voucher_start',
        	'deals_voucher_duration'	=> 'nullable',

            'data.rule.deals_type' 	=> 'nullable',
            'data.rule.deals_voucher_type' 	=> 'required',
            'data.rule.deals_promo_id_type' 	=> 'nullable',
            'data.rule.deals_promo_id' 	=> 'nullable',
            'data.rule.deals_title' 	=> 'required',
            'data.rule.promo_type' 	=> 'required',

            'data.outlet'	=> 'nullable',
            'data.content'	=> 'nullable',
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

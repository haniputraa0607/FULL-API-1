<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class users_phone_pin_new extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
	public function rules()
	{
		return [
			'phone'			=> 'required|string|max:18',
			'pin_old'		=> 'required|string|digits:6',
			'pin_new'		=> 'required|string|digits:6',
			'device_id'		=> 'max:200',
			'device_token'	=> 'max:225'
        ];
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

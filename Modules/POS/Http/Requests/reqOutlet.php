<?php

namespace Modules\POS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class reqOutlet extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'api_key'                   => 'required',
            'api_secret'                => 'required',
            'store'                     => 'required|array',
            'store.*.store_code'        => 'required',
            'store.*.store_name'        => 'required',
            'store.*.store_status'      => 'required|in:Active,Inactive,Not Active',
            'store.*.store_address'     => 'required',
            'store.*.store_latitude'    => 'required',
            'store.*.store_longitude'   => 'required',
            'store.*.store_schedule'    => 'required|array'
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

<?php

namespace Modules\Transaction\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckTransaction extends FormRequest
{
    public function rules()
    {
        return [
            'type'                                    => 'sometimes|in:Pickup Order,GO-SEND,Grab,Outlet Delivery',
            'payment_type'                            => 'nullable|in:Midtrans,Manual,Balance,Ovo,Ipay88,Shopeepay,Xendit',
            'destination.id_user_address'             => 'nullable|sometimes',
            'destination.short_address'               => 'nullable|sometimes',
            'destination.address'                     => 'required_if:type,GO-SEND,Grab,Outlet Delivery',
            'destination.latitude'                    => 'required_if:type,GO-SEND,Grab,Outlet Delivery',
            'destination.longitude'                   => 'required_if:type,GO-SEND,Grab,Outlet Delivery',
            'destination.notes'                       => 'nullable|sometimes',
            'id_outlet'                               => 'required|numeric',
            'pickup_at'                               => 'nullable|date_format:H:i',
            'items'                                   => 'sometimes|nullable|array',
            'items.*.id_product_group'                => 'required|numeric',
            'items.*.id_brand'                        => 'required|numeric',
            'items.*.variants'                        => 'required|array',
            'items.*.variants.*'                      => 'required|numeric',
            'items.*.qty'                             => 'required|numeric|min:1',
            'items.*.note'                            => 'sometimes|nullable|string',
            'items.*.modifiers'                       => 'sometimes|nullable|array',
            'items.*.modifiers.*.qty'                 => 'required|numeric|min:1',
            'items.*.modifiers.*.id_product_modifier' => 'required|numeric',
        ];
    }

    public function authorize()
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['status' => 'fail', 'messages' => $validator->errors()->all()], 200));
    }

    protected function validationData()
    {
        return $this->json()->all();
    }
}

<?php

namespace Modules\Transaction\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class NewTransaction extends FormRequest
{
    public function rules()
    {
        return [
            'id_outlet'                => 'required|integer',
            'type'                     => 'required|in:Delivery,Pickup Order,GO-SEND,Internal Delivery',
            'notes'                    => 'nullable|string',
            'pickup_type'              => 'required_if:type,Pickup Order|in:set time,right now,at arrival',
            'pickup_at'                => 'required_if:pickup_type,set time|date_format:Y-m-d H:i:s',
            'payment_type'             => 'nullable|in:Midtrans,Manual,Balance,Ovo,Cimb,Ipay88,Shopeepay',
            
            'item'                                   => 'required|array',
            'item.*.id_product_group'                => 'required|numeric',
            'item.*.id_brand'                        => 'required|numeric',
            'item.*.variants'                        => 'required|array',
            'item.*.variants.*'                      => 'required|numeric',
            'item.*.qty'                             => 'required|numeric|min:1',
            'item.*.note'                            => 'sometimes|nullable|string',
            'item.*.modifiers'                       => 'sometimes|nullable|array',
            // 'item.*.modifiers.*.qty'                 => 'required|numeric|min:1',
            // 'item.*.modifiers.*.id_product_modifier' => 'required|numeric', // comment for ios compatibility
            
            'destination.id_user_address'             => 'nullable|sometimes',
            'destination.short_address'               => 'nullable|sometimes',
            'destination.name'                        => 'nullable|sometimes',
            'destination.address'                     => 'required_if:type,GO-SEND,Grab,Internal Delivery',
            'destination.latitude'                    => 'required_if:type,GO-SEND,Grab,Internal Delivery',
            'destination.longitude'                   => 'required_if:type,GO-SEND,Grab,Internal Delivery',
            'destination.notes'                       => 'nullable|sometimes',

            // 'id_manual_payment_method' => 'required_if:payment_type,Manual|integer',
            // 'payment_date'             => 'required_if:payment_type,Manual|date_format:Y-m-d',
            // 'payment_time'             => 'required_if:payment_type,Manual|date_format:H:i:s',
            // 'payment_bank'             => 'required_if:payment_type,Manual|string',
            // 'payment_method'           => 'required_if:payment_type,Manual|string',
            // 'payment_method'           => 'required_if:payment_type,Manual|string',
            // 'payment_account_number'   => 'required_if:payment_type,Manual|numeric',
            // 'payment_account_name'     => 'required_if:payment_type,Manual|string',
            // 'payment_receipt_image'    => 'required_if:payment_type,Manual',
        ];
    }

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

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RechargeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'in:manual,alipay,wechat'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => '充值金额不能为空',
            'amount.integer' => '充值金额必须为整数',
            'amount.min' => '充值金额必须大于0',
            'payment_method.in' => '支付方式无效',
        ];
    }
}

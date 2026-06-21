<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => '订单标题不能为空',
            'amount.required' => '订单金额不能为空',
            'amount.integer' => '订单金额必须为整数',
            'amount.min' => '订单金额必须大于0',
        ];
    }
}

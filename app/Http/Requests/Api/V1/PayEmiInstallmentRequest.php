<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class PayEmiInstallmentRequest
 *
 * Validates transition of an EMI installment from pending to paid.
 *
 * @package App\Http\Requests\Api\V1
 */
class PayEmiInstallmentRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'payment_date'   => 'nullable|date',
            'payment_method' => 'nullable|string|in:cash,cheque,bank_transfer,mobile_banking,others',
            'remarks'        => 'nullable|string|max:1000',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class UpdateEmiInstallmentRequest
 *
 * Validates updates to an existing EMI installment record.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateEmiInstallmentRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'emi_amount'     => 'sometimes|required|numeric|min:0.01',
            'savings_amount' => 'nullable|numeric|min:0',
            'payment_date'   => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|string|in:cash,cheque,bank_transfer,mobile_banking,others',
            'remarks'        => 'nullable|string|max:1000',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('emi_amount') && is_string($this->emi_amount)) {
            $merge['emi_amount'] = str_replace(',', '', trim($this->emi_amount));
        }
        if ($this->has('savings_amount') && is_string($this->savings_amount)) {
            $merge['savings_amount'] = str_replace(',', '', trim($this->savings_amount));
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}

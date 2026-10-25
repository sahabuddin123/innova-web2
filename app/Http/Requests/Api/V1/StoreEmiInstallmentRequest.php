<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * Class StoreEmiInstallmentRequest
 *
 * Validates manual creation of an EMI installment or repayment record.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreEmiInstallmentRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'bank_loan_id'   => [
                'required',
                'integer',
                Rule::exists('bank_loans', 'id')->whereNull('deleted_at'),
            ],
            'emi_amount'     => 'required|numeric|min:0.01',
            'savings_amount' => 'nullable|numeric|min:0',
            'payment_date'   => 'required|date',
            'payment_method' => 'nullable|string|in:cash,cheque,bank_transfer,mobile_banking,others',
            'status'         => 'nullable|string|in:pending,paid',
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

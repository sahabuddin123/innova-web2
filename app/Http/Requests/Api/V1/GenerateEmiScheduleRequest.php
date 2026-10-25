<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class GenerateEmiScheduleRequest
 *
 * Validates parameters for generating an automated EMI installment schedule.
 *
 * @package App\Http\Requests\Api\V1
 */
class GenerateEmiScheduleRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'start_date'                      => 'nullable|date',
            'savings_amount_per_installment'  => 'nullable|numeric|min:0',
            'savings_per_installment'         => 'nullable|numeric|min:0',
            'payment_method'                  => 'nullable|string|in:cash,cheque,bank_transfer,mobile_banking,others',
            'overwrite_pending'               => 'nullable|boolean',
            'overwrite_existing'              => 'nullable|boolean',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('savings_amount_per_installment') && is_string($this->savings_amount_per_installment)) {
            $merge['savings_amount_per_installment'] = str_replace(',', '', trim($this->savings_amount_per_installment));
        }
        if ($this->has('savings_per_installment') && is_string($this->savings_per_installment)) {
            $merge['savings_per_installment'] = str_replace(',', '', trim($this->savings_per_installment));
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}

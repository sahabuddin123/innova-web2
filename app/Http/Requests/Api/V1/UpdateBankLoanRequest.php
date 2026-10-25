<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * Class UpdateBankLoanRequest
 *
 * Validates updates to bank loan records.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateBankLoanRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'shareholder_id'    => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('shareholders', 'id')->whereNull('deleted_at'),
            ],
            'bank_name'         => 'sometimes|required|string|max:191',
            'loan_amount'       => 'sometimes|required|numeric|min:0.01',
            'interest_rate'     => 'sometimes|required|numeric|min:0|max:100',
            'loan_term_months'  => 'sometimes|required|integer|min:1|max:600',
            'installment_count' => 'sometimes|required|integer|min:1',
            'loan_date'         => 'sometimes|required|date',
            'maturity_date'     => 'sometimes|required|date|after_or_equal:loan_date',
            'savings_amount'    => 'nullable|numeric|min:0',
            'status'            => 'sometimes|required|string|in:active,closed,defaulted',
            'remarks'           => 'nullable|string|max:1000',
        ];
    }

    /**
     * Prepare data for validation by stripping commas from numeric amounts.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('loan_amount') && is_string($this->loan_amount)) {
            $merge['loan_amount'] = str_replace(',', '', trim($this->loan_amount));
        }
        if ($this->has('interest_rate') && is_string($this->interest_rate)) {
            $merge['interest_rate'] = str_replace(',', '', trim($this->interest_rate));
        }
        if ($this->has('savings_amount') && is_string($this->savings_amount)) {
            $merge['savings_amount'] = str_replace(',', '', trim($this->savings_amount));
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}

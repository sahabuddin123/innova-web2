<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * Class StoreBankLoanRequest
 *
 * Validates bank loan creation with comma stripping on monetary and numeric inputs.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreBankLoanRequest extends ApiRequest
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
                'required',
                'integer',
                Rule::exists('shareholders', 'id')->whereNull('deleted_at'),
            ],
            'bank_name'         => 'required|string|max:191',
            'loan_amount'       => 'required|numeric|min:0.01',
            'interest_rate'     => 'required|numeric|min:0|max:100',
            'loan_term_months'  => 'required|integer|min:1|max:600',
            'installment_count' => 'required|integer|min:1',
            'loan_date'         => 'required|date',
            'maturity_date'     => 'required|date|after_or_equal:loan_date',
            'savings_amount'    => 'nullable|numeric|min:0',
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

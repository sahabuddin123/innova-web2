<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * Class StoreInvestmentWithdrawRequest
 *
 * Validates administrative capital investment withdrawal creation requests
 * with automatic comma-amount sanitization.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreInvestmentWithdrawRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'shareholder_id' => [
                'required',
                'integer',
                Rule::exists('shareholders', 'id')->whereNull('deleted_at'),
            ],
            'amount'         => 'required|numeric|min:0.01',
            'withdraw_date'  => 'nullable|date',
            'method'         => 'nullable|string|max:191',
            'receiver_name'  => 'nullable|string|max:191',
            'branch_name'    => 'nullable|string|max:191',
            'account_number' => 'nullable|string|max:191',
            'pay_by'         => 'nullable|string|max:191',
            'remarks'        => 'nullable|string|max:1000',
            'attachment'     => 'nullable|string|max:255',
        ];
    }

    /**
     * Prepare data for validation by stripping commas from amount and trimming strings.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && is_string($this->amount)) {
            $this->merge([
                'amount' => str_replace(',', '', trim($this->amount)),
            ]);
        }
    }
}

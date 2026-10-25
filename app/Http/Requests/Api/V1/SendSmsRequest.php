<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class SendSmsRequest
 *
 * Validates direct SMS dispatch payload.
 *
 * @package App\Http\Requests\Api\V1
 */
class SendSmsRequest extends ApiRequest
{
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('recipient') && $this->recipient !== null) {
            $this->merge([
                'recipient' => (string) $this->recipient,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'recipient' => [
                'required',
                'string',
                'regex:/^(?:\+88|88)?(01[3-9]\d{8})$/',
            ],
            'message' => [
                'required',
                'string',
                'min:1',
                'max:1000',
            ],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'recipient.required' => 'The recipient phone number is required.',
            'recipient.regex'    => 'The recipient must be a valid Bangladeshi mobile number (e.g. 01712345678, +8801712345678, or 8801712345678).',
            'message.required'   => 'The SMS message body is required.',
            'message.max'        => 'The SMS message body may not exceed 1000 characters.',
        ];
    }
}

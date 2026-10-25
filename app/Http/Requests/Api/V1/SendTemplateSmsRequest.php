<?php

namespace App\Http\Requests\Api\V1;

use App\Services\SmsService;
use Illuminate\Validation\Rule;

/**
 * Class SendTemplateSmsRequest
 *
 * Validates template-based SMS dispatch payload.
 *
 * @package App\Http\Requests\Api\V1
 */
class SendTemplateSmsRequest extends ApiRequest
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
        $validKeys = array_keys(SmsService::TEMPLATES);

        return [
            'template_key' => [
                'required',
                'string',
                Rule::in($validKeys),
            ],
            'student_id' => [
                'nullable',
                'integer',
                'exists:students,id',
            ],
            'trainee_id' => [
                'nullable',
                'integer',
            ],
            'recipient' => [
                'nullable',
                'string',
                'regex:/^(?:\+88|88)?(01[3-9]\d{8})$/',
            ],
            'params' => [
                'nullable',
                'array',
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
            'template_key.required' => 'The SMS template key is required.',
            'template_key.in'       => 'The selected template key is not supported by the system.',
            'recipient.regex'       => 'The recipient must be a valid Bangladeshi mobile number.',
            'student_id.exists'     => 'The selected student does not exist.',
        ];
    }
}

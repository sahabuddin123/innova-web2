<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class UpdateSmsSettingsRequest
 *
 * Validates updates specifically targeting SMS provider settings.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateSmsSettingsRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'sms_enabled' => [
                'nullable',
                'in:0,1,true,false',
            ],
            'sms_url' => [
                'nullable',
                'url',
                'max:255',
            ],
            'sms_api_key' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sms_sender_id' => [
                'nullable',
                'string',
                'max:50',
            ],
            'sms_provider' => [
                'nullable',
                'string',
                'max:50',
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
            'sms_url.url'       => 'The SMS gateway URL must be a valid URL.',
            'sms_sender_id.max' => 'The sender ID may not exceed 50 characters.',
        ];
    }
}

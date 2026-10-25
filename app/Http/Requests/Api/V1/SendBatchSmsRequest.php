<?php

namespace App\Http\Requests\Api\V1;

use App\Services\SmsService;
use Illuminate\Validation\Rule;

/**
 * Class SendBatchSmsRequest
 *
 * Validates bulk SMS dispatch to students of an academic batch.
 *
 * @package App\Http\Requests\Api\V1
 */
class SendBatchSmsRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $validKeys = array_keys(SmsService::TEMPLATES);

        return [
            'batch_id' => [
                'required',
                'integer',
                'exists:batchs,id',
            ],
            'template_key' => [
                'nullable',
                'string',
                Rule::in($validKeys),
                'required_without:message',
            ],
            'message' => [
                'nullable',
                'string',
                'min:1',
                'max:1000',
                'required_without:template_key',
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
            'batch_id.required'                 => 'The academic batch ID is required.',
            'batch_id.exists'                   => 'The specified batch does not exist.',
            'template_key.required_without'     => 'Either a template_key or a custom message must be provided.',
            'message.required_without'          => 'Either a custom message or a template_key must be provided.',
            'template_key.in'                   => 'The selected template key is not supported by the system.',
            'message.max'                       => 'The message content may not exceed 1000 characters.',
        ];
    }
}

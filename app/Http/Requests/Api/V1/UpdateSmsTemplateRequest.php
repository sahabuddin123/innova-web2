<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class UpdateSmsTemplateRequest
 *
 * Validates updates to an approved SMS template.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateSmsTemplateRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'template_text' => [
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
            'template_text.required' => 'The template message text is required.',
            'template_text.max'      => 'The template text may not exceed 1000 characters.',
        ];
    }
}

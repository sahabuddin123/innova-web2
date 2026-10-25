<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Setting;
use Illuminate\Contracts\Validation\Validator;

/**
 * Class UpdateSettingsRequest
 *
 * Validates updates to system settings, ensuring arbitrary configuration keys
 * and .env modifications cannot be injected through the API.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateSettingsRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array',
                'min:1',
            ],
        ];
    }

    /**
     * Perform additional validation logic to ensure all keys are permitted.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $settings = $this->input('settings', []);

            if (!is_array($settings)) {
                return;
            }

            foreach ($settings as $key => $value) {
                if (!is_string($key)) {
                    $validator->errors()->add('settings', 'Setting keys must be valid strings.');
                    break;
                }

                if (!Setting::isPermittedKey($key)) {
                    $validator->errors()->add(
                        "settings.{$key}",
                        "The setting key '{$key}' is not permitted for modification via the API."
                    );
                }

                // If value is a string, enforce safety limit
                if (is_string($value) && strlen($value) > 10000) {
                    $validator->errors()->add(
                        "settings.{$key}",
                        "The setting value for '{$key}' exceeds maximum permitted length."
                    );
                }
            }
        });
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'The settings payload is required.',
            'settings.array'    => 'The settings must be provided as a key-value object.',
            'settings.min'      => 'At least one setting must be provided for update.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AssetRegistration;
use Illuminate\Validation\Rule;

/**
 * Class UpdateTraineeStatusRequest
 *
 * Validates transition of a trainee's qualification / registration status.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateTraineeStatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'registration_status' => [
                'required',
                'string',
                Rule::in(AssetRegistration::ALLOWED_STATUSES),
            ],
            'remarks'             => 'nullable|string|max:1000',
        ];
    }
}

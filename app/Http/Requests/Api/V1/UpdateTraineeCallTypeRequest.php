<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AssetRegistration;
use Illuminate\Validation\Rule;

/**
 * Class UpdateTraineeCallTypeRequest
 *
 * Validates updates to a trainee's call screening milestone.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateTraineeCallTypeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'call_type' => [
                'required',
                'string',
                Rule::in(AssetRegistration::ALLOWED_CALL_TYPES),
            ],
            'remarks'   => 'nullable|string|max:1000',
        ];
    }
}

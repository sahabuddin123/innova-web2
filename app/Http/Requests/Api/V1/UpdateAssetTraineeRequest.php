<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AssetRegistration;
use Illuminate\Validation\Rule;

/**
 * Class UpdateAssetTraineeRequest
 *
 * Validates updates to an ASSET / SEIP candidate registration record.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateAssetTraineeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'course'              => 'nullable|string|max:191',
            'full_name'           => 'nullable|string|max:255',
            'father_name'         => 'nullable|string|max:255',
            'mother_name'         => 'nullable|string|max:255',
            'mobile'              => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'whatsapp'            => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'email'               => 'nullable|email|max:191',
            'religion'            => 'nullable|string|max:100',
            'gender'              => 'nullable|string|max:50',
            'dob'                 => 'nullable|date',
            'nid'                 => 'nullable|string|max:50',
            'employment_status'   => 'nullable|string|max:100',
            'address'             => 'nullable|string|max:2000',
            'education'           => 'nullable|string|max:191',
            'board'               => 'nullable|string|max:191',
            'subject'             => 'nullable|string|max:191',
            'passing_year'        => 'nullable|string|max:10',
            'photo'               => 'nullable|string|max:255',
            'registration_status' => [
                'nullable',
                'string',
                Rule::in(AssetRegistration::ALLOWED_STATUSES),
            ],
            'call_type'           => [
                'nullable',
                'string',
                Rule::in(AssetRegistration::ALLOWED_CALL_TYPES),
            ],
        ];
    }
}

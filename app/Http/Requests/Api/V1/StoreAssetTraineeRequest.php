<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AssetRegistration;
use Illuminate\Validation\Rule;

/**
 * Class StoreAssetTraineeRequest
 *
 * Validates registration and intake for an ASSET / SEIP government training candidate.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreAssetTraineeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'course'              => 'required|string|max:191',
            'full_name'           => 'required|string|max:255',
            'father_name'         => 'required|string|max:255',
            'mother_name'         => 'required|string|max:255',
            'mobile'              => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'whatsapp'            => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'email'               => 'required|email|max:191',
            'religion'            => 'required|string|max:100',
            'gender'              => 'required|string|max:50',
            'dob'                 => 'required|date',
            'nid'                 => 'required|string|max:50',
            'employment_status'   => 'required|string|max:100',
            'address'             => 'required|string|max:2000',
            'education'           => 'required|string|max:191',
            'board'               => 'required|string|max:191',
            'subject'             => 'required|string|max:191',
            'passing_year'        => 'required|string|max:10',
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

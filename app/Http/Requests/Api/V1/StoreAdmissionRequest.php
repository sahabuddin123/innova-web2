<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class StoreAdmissionRequest
 *
 * Validates public online admission inquiry applications.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreAdmissionRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'username'   => 'required|string|max:191',
            'phone'      => 'required|string|max:50|unique:admissions,phone',
            'courseId'   => 'required|integer|exists:courses,id',
            'courseType' => 'required|integer|in:0,1',
            'email'      => 'nullable|email|max:191',
        ];
    }
}

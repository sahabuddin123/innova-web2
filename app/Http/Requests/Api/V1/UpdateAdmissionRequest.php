<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class UpdateAdmissionRequest
 *
 * Validates administrative updates for online admission records.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateAdmissionRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'username'   => 'sometimes|required|string|max:191',
            'phone'      => 'sometimes|required|string|max:50',
            'courseId'   => 'sometimes|required|integer|exists:courses,id',
            'courseType' => 'sometimes|required|integer|in:0,1',
            'email'      => 'nullable|email|max:191',
            'isActive'   => 'nullable|integer|in:0,1,2,3,4,5,6',
        ];
    }
}

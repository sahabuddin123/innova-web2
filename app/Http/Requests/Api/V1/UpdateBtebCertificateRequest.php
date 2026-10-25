<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class UpdateBtebCertificateRequest
 *
 * Validates updates to an existing BTEB board certificate.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateBtebCertificateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'session_id' => 'sometimes|required|integer|exists:sessions,id',
            'regNo'      => 'sometimes|required|string|max:191',
            'cno'        => 'nullable|string|max:191',
            'pDate'      => 'nullable|date',
            'Result'     => 'nullable|string|max:50',
            'recevier'   => 'nullable|string|max:191',
            'dDate'      => 'nullable|date',
            'IsActive'   => 'sometimes|required|in:0,1',
        ];
    }
}

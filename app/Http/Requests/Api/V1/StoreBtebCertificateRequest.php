<?php

namespace App\Http\Requests\Api\V1;

/**
 * Class StoreBtebCertificateRequest
 *
 * Validates recording of a BTEB board certificate.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreBtebCertificateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'student_id' => 'required|integer|exists:students,id',
            'session_id' => 'required|integer|exists:sessions,id',
            'regNo'      => 'required|string|max:191',
            'cno'        => 'nullable|string|max:191',
            'pDate'      => 'nullable|date',
            'Result'     => 'nullable|string|max:50',
            'recevier'   => 'nullable|string|max:191',
            'dDate'      => 'nullable|date',
            'IsActive'   => 'required|in:0,1',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Certificate_icbd;

/**
 * Class StoreCertificateIcbdRequest
 *
 * Validates issuance of a new ICBD institutional certificate.
 *
 * @package App\Http\Requests\Api\V1
 */
class StoreCertificateIcbdRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'student_id'           => 'required|integer|exists:students,id',
            'course_id'            => 'required|integer|exists:courses,id',
            'batch_id'             => 'required|integer|exists:batchs,id',
            'certificate_no'       => 'nullable|string|max:100|unique:certificates_icbd,certificate_no',
            'theory_hours'         => 'nullable|integer|min:0|max:10000',
            'practical_hours'      => 'nullable|integer|min:0|max:10000',
            'intern_hours'         => 'nullable|integer|min:0|max:10000',
            'result'               => 'nullable|string|max:50',
            'issue_date'           => 'nullable|date',
            'certificate_receiver' => 'nullable|string|max:150',
            'delivery_date'        => 'nullable|date',
            'is_active'            => 'required|integer|in:' . implode(',', Certificate_icbd::ALLOWED_STATUSES),
        ];
    }
}

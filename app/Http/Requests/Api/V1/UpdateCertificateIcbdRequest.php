<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Certificate_icbd;

/**
 * Class UpdateCertificateIcbdRequest
 *
 * Validates updates to an existing ICBD institutional certificate.
 *
 * @package App\Http\Requests\Api\V1
 */
class UpdateCertificateIcbdRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id') ?? $this->id;

        return [
            'course_id'            => 'sometimes|required|integer|exists:courses,id',
            'batch_id'             => 'sometimes|required|integer|exists:batchs,id',
            'certificate_no'       => 'sometimes|required|string|max:100|unique:certificates_icbd,certificate_no,' . $id,
            'theory_hours'         => 'nullable|integer|min:0|max:10000',
            'practical_hours'      => 'nullable|integer|min:0|max:10000',
            'intern_hours'         => 'nullable|integer|min:0|max:10000',
            'result'               => 'nullable|string|max:50',
            'issue_date'           => 'nullable|date',
            'certificate_receiver' => 'nullable|string|max:150',
            'delivery_date'        => 'nullable|date',
            'is_active'            => 'sometimes|required|integer|in:' . implode(',', Certificate_icbd::ALLOWED_STATUSES),
        ];
    }
}
